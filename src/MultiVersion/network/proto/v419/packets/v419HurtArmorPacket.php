<?php

namespace MultiVersion\network\proto\v419\packets;

use pocketmine\network\mcpe\protocol\HurtArmorPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419HurtArmorPacket extends HurtArmorPacket{

	public static function fromLatest(HurtArmorPacket $pk) : self{
		$npk = new self();
		$npk->cause = $pk->cause;
		$npk->health = $pk->health;
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->cause = $in->getVarInt();
		$this->health = $in->getVarInt();
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putVarInt($this->cause);
		$out->putVarInt($this->health);
	}
}