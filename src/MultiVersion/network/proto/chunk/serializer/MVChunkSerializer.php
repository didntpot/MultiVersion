<?php

namespace MultiVersion\network\proto\chunk\serializer;

use MultiVersion\network\proto\PacketSerializerFactory;
use MultiVersion\network\proto\static\MVBlockTranslator;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;

interface MVChunkSerializer{

	public function getSubChunkCount(Chunk $chunk) : int;

	public function serializeFullChunk(Chunk $chunk, MVBlockTranslator $blockTranslator, PacketSerializerFactory $factory, ?string $tiles = null) : string;

	public function serializeSubChunk(SubChunk $subChunk, MVBlockTranslator $blockTranslator, PacketSerializer $stream, bool $persistentBlockStates) : void;

	public function serializeTiles(Chunk $chunk) : string;
}