<?php

namespace MultiVersion\network\proto\chunk\serializer;

use MultiVersion\network\proto\PacketSerializerFactory;
use MultiVersion\network\proto\static\MVBlockTranslator;
use pocketmine\block\tile\Spawnable;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;

class MultiLayeredChunkSerializer implements MVChunkSerializer{

	public function getSubChunkCount(Chunk $chunk) : int{
		return min(ChunkSerializer::getSubChunkCount($chunk), 16);
	}

	public function serializeFullChunk(Chunk $chunk, MVBlockTranslator $blockTranslator, PacketSerializerFactory $factory, ?string $tiles = null) : string{
		$stream = $factory->newEncoder($factory->newSerializerContext());
		$subChunkCount = $this->getSubChunkCount($chunk);
		for($y = 0; $y < $subChunkCount; ++$y){
			$this->serializeSubChunk($chunk->getSubChunk($y), $blockTranslator, $stream, false);
		}

		$biome = str_repeat(chr(BiomeIds::OCEAN), 256); //2d biome array
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$biome[($z << 4) | $x] = chr($chunk->getBiomeId($x, $chunk->getHighestBlockAt($x, $z) ?? BiomeIds::OCEAN, $z));
			}
		}
		$stream->put($biome);

		$stream->putByte(0); //border block array count
		//Border block entry format: 1 byte (4 bits X, 4 bits Z). These are however useless since they crash the regular client.

		if($tiles !== null){
			$stream->put($tiles);
		}else{
			$stream->put($this->serializeTiles($chunk));
		}
		return $stream->getBuffer();
	}

	public function serializeSubChunk(SubChunk $subChunk, MVBlockTranslator $blockTranslator, PacketSerializer $stream, bool $persistentBlockStates) : void{
		$layers = $subChunk->getBlockLayers();
		$stream->putByte(8); //version

		$stream->putByte(count($layers));

		$blockStateDictionary = $blockTranslator->getBlockStateDictionary();

		foreach($layers as $blocks){
			$bitsPerBlock = $blocks->getBitsPerBlock();
			$words = $blocks->getWordArray();
			$stream->putByte(($bitsPerBlock << 1) | ($persistentBlockStates ? 0 : 1));
			$stream->put($words);
			$palette = $blocks->getPalette();

			if($bitsPerBlock !== 0){
				//these LSHIFT by 1 uvarints are optimizations: the client expects zigzag varints here
				//but since we know they are always unsigned, we can avoid the extra fcall overhead of
				//zigzag and just shift directly.
				$stream->putUnsignedVarInt(count($palette) << 1); //yes, this is intentionally zigzag
			}
			if($persistentBlockStates){
				$nbtSerializer = new NetworkNbtSerializer();
				foreach($palette as $p){
					//TODO: introduce a binary cache for this
					$state = $blockStateDictionary->generateDataFromStateId($blockTranslator->internalIdToNetworkId($p));
					if($state === null){
						$state = $blockTranslator->getFallbackStateData();
					}

					$stream->put($nbtSerializer->write(new TreeRoot($state->toNbt())));
				}
			}else{
				foreach($palette as $p){
					$stream->put(Binary::writeUnsignedVarInt($blockTranslator->internalIdToNetworkId($p) << 1));
				}
			}
		}
	}

	public function serializeTiles(Chunk $chunk) : string{
		$stream = new BinaryStream();
		foreach($chunk->getTiles() as $tile){
			if($tile instanceof Spawnable){
				$stream->put($tile->getSerializedSpawnCompound()->getEncodedNbt());
			}
		}

		return $stream->getBuffer();
	}
}