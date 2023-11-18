<?php

namespace MultiVersion\network\proto\v419\packets;

use pocketmine\network\mcpe\protocol\NpcRequestPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419NpcRequestPacket extends NpcRequestPacket{

	protected function decodePayload(PacketSerializer $in) : void{
		$this->actorRuntimeId = $in->getActorRuntimeId();
		$this->requestType = $in->getByte();
		$this->commandString = $in->getString();
		$this->actionIndex = $in->getByte();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putActorRuntimeId($this->actorRuntimeId);
		$out->putByte($this->requestType);
		$out->putString($this->commandString);
		$out->putByte($this->actionIndex);
	}
}
