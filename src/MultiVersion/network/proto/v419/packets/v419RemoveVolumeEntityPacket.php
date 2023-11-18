<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\RemoveVolumeEntityPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use ReflectionException;

class v419RemoveVolumeEntityPacket extends RemoveVolumeEntityPacket{

	/**
	 * @throws ReflectionException
	 */
	public static function fromLatest(RemoveVolumeEntityPacket $packet) : self{
		$npk = new self();
		ReflectionUtils::setProperty(RemoveVolumeEntityPacket::class, $npk, "entityNetId", $packet->getEntityNetId());
		return $npk;
	}

	/**
	 * @throws ReflectionException
	 */
	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(RemoveVolumeEntityPacket::class, $this, "entityNetId", $in->getUnsignedVarInt());
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt($this->getEntityNetId());
	}
}