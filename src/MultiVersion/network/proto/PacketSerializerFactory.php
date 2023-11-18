<?php

namespace MultiVersion\network\proto;

use MultiVersion\network\proto\chunk\serializer\MVChunkSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;

interface PacketSerializerFactory{

	public function newEncoder(PacketSerializerContext $context) : PacketSerializer;

	public function newDecoder(string $buffer, int $offset, PacketSerializerContext $context) : PacketSerializer;

	public function newSerializerContext() : PacketSerializerContext;

	public function getChunkSerializer() : MVChunkSerializer;
}