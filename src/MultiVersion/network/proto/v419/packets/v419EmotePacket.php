<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\EmotePacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use ReflectionException;

class v419EmotePacket extends EmotePacket{

	/**
	 * @throws ReflectionException
	 */
	public static function fromLatest(EmotePacket $pk) : self{
		$npk = new self();
		ReflectionUtils::setProperty(EmotePacket::class, $npk, "actorRuntimeId", $pk->getActorRuntimeId());
		ReflectionUtils::setProperty(EmotePacket::class, $npk, "emoteId", $pk->getEmoteId());
		ReflectionUtils::setProperty(EmotePacket::class, $npk, "flags", $pk->getFlags());
		return $npk;
	}

	/**
	 * @throws ReflectionException
	 */
	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(EmotePacket::class, $this, "actorRuntimeId", $in->getActorRuntimeId());
		ReflectionUtils::setProperty(EmotePacket::class, $this, "emoteId", $in->getString());
		ReflectionUtils::setProperty(EmotePacket::class, $this, "flags", $in->getByte());
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putActorRuntimeId($this->getActorRuntimeId());
		$out->putString($this->getEmoteId());
		$out->putByte($this->getFlags());
	}
}