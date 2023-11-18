<?php

namespace MultiVersion\network\proto\v486\packets;

use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v486RequestChunkRadiusPacket extends RequestChunkRadiusPacket{

	protected function decodePayload(PacketSerializer $in) : void{
		$this->radius = $in->getVarInt();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putVarInt($this->radius);
	}
}
