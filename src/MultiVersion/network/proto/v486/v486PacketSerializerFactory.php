<?php

namespace MultiVersion\network\proto\v486;

use MultiVersion\network\proto\chunk\serializer\MVChunkSerializer;
use MultiVersion\network\proto\MVPacketSerializer;
use MultiVersion\network\proto\PacketSerializerFactory;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;

class v486PacketSerializerFactory implements PacketSerializerFactory{

	public function __construct(
		private ItemTypeDictionary $itemTypeDictionary,
		private MVChunkSerializer $chunkSerializer
	){

	}

	public function newEncoder(PacketSerializerContext $context) : MVPacketSerializer{
		return v486PacketSerializer::newEncoder($context);
	}

	public function newDecoder(string $buffer, int $offset, PacketSerializerContext $context) : MVPacketSerializer{
		return v486PacketSerializer::newDecoder($buffer, $offset, $context);
	}

	public function newSerializerContext() : PacketSerializerContext{
		return new PacketSerializerContext($this->itemTypeDictionary);
	}

	public function getChunkSerializer() : MVChunkSerializer{
		return $this->chunkSerializer;
	}
}