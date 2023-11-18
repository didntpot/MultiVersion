<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use ReflectionException;

class v419NetworkSettingsPacket extends NetworkSettingsPacket{

	/**
	 * @throws ReflectionException
	 */
	public static function fromLatest(NetworkSettingsPacket $packet) : self{
		$npk = new self();
		ReflectionUtils::setProperty(NetworkSettingsPacket::class, $npk, "compressionThreshold", $packet->getCompressionThreshold());
		return $npk;
	}

	/**
	 * @throws ReflectionException
	 */
	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(NetworkSettingsPacket::class, $this, "compressionThreshold", $in->getLShort());
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putLShort($this->getCompressionThreshold());
	}
}
