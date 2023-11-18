<?php

namespace MultiVersion\network\proto\v419\packets;

use pocketmine\network\mcpe\protocol\ActorPickRequestPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419ActorPickRequestPacket extends ActorPickRequestPacket{

	protected function decodePayload(PacketSerializer $in) : void{
		$this->actorUniqueId = $in->getLLong();
		$this->hotbarSlot = $in->getByte();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putLLong($this->actorUniqueId);
		$out->putByte($this->hotbarSlot);
	}
}
