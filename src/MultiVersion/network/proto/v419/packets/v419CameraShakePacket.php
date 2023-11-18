<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\CameraShakePacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419CameraShakePacket extends CameraShakePacket{

	public static function fromLatest(CameraShakePacket $pk) : self{
		$npk = new self();
		ReflectionUtils::setProperty(CameraShakePacket::class, $npk, "intensity", $pk->getIntensity());
		ReflectionUtils::setProperty(CameraShakePacket::class, $npk, "duration", $pk->getDuration());
		ReflectionUtils::setProperty(CameraShakePacket::class, $npk, "shakeType", $pk->getShakeType());
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(CameraShakePacket::class, $this, "intensity", $in->getLFloat());
		ReflectionUtils::setProperty(CameraShakePacket::class, $this, "duration", $in->getLFloat());
		ReflectionUtils::setProperty(CameraShakePacket::class, $this, "shakeType", $in->getByte());
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putLFloat($this->getIntensity());
		$out->putLFloat($this->getDuration());
		$out->putByte($this->getShakeType());
	}
}
