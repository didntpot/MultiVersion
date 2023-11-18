<?php

namespace MultiVersion\network\proto;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;

abstract class MVPacketSerializer extends PacketSerializer{

	protected int $shieldItemRuntimeId;

	protected function __construct(protected PacketSerializerContext $packetSerializerContext, string $buffer = "", int $offset = 0){
		parent::__construct($packetSerializerContext, $buffer, $offset);
		$this->shieldItemRuntimeId = $this->packetSerializerContext->getItemDictionary()->fromStringId("minecraft:shield");
	}

	final public static function newEncoder(PacketSerializerContext $context) : self{
		return new static($context);
	}

	final public static function newDecoder(string $buffer, int $offset, PacketSerializerContext $context) : self{
		return new static($context, $buffer, $offset);
	}

	final protected function getContext() : PacketSerializerContext{
		return $this->packetSerializerContext;
	}
}