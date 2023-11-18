<?php

namespace MultiVersion\network\proto\latest;

use MultiVersion\network\proto\chunk\serializer\MVChunkSerializer;
use MultiVersion\network\proto\PacketSerializerFactory;
use MultiVersion\network\proto\static\MVBlockTranslator;
use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\data\bedrock\LegacyBiomeIdToStringIdMap;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\utils\Binary;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use ReflectionException;

class LatestChunkSerializerWrapper implements MVChunkSerializer{

	public function getSubChunkCount(Chunk $chunk) : int{
		return ChunkSerializer::getSubChunkCount($chunk);
	}

	/**
	 * @throws ReflectionException
	 */
	public function serializeFullChunk(Chunk $chunk, MVBlockTranslator $blockTranslator, PacketSerializerFactory $factory, ?string $tiles = null) : string{
		$stream = PacketSerializer::encoder($factory->newSerializerContext());

		$subChunkCount = self::getSubChunkCount($chunk);
		$writtenCount = 0;
		for($y = Chunk::MIN_SUBCHUNK_INDEX; $writtenCount < $subChunkCount; ++$y, ++$writtenCount){
			self::serializeSubChunk($chunk->getSubChunk($y), $blockTranslator, $stream, false);
		}

		$biomeIdMap = LegacyBiomeIdToStringIdMap::getInstance();
		//all biomes must always be written :(
		for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; ++$y){
			ReflectionUtils::invokeStatic(ChunkSerializer::class, "serializeBiomePalette", $chunk->getSubChunk($y)->getBiomeArray(), $biomeIdMap, $stream);
		}

		$stream->putByte(0); //border block array count
		//Border block entry format: 1 byte (4 bits X, 4 bits Z). These are however useless since they crash the regular client.

		if($tiles !== null){
			$stream->put($tiles);
		}else{
			$stream->put(self::serializeTiles($chunk));
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
		return ChunkSerializer::serializeTiles($chunk);
	}
}