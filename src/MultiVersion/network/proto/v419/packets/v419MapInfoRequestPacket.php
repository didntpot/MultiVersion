<?php

namespace MultiVersion\network\proto\v419\packets;

use pocketmine\network\mcpe\protocol\MapInfoRequestPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419MapInfoRequestPacket extends MapInfoRequestPacket{

	protected function decodePayload(PacketSerializer $in) : void{
		$this->mapId = $in->getActorUniqueId();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putActorUniqueId($this->mapId);
	}
}
