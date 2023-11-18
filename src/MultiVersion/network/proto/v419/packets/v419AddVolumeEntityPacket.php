<?php

declare(strict_types=1);

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\AddVolumeEntityPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;

class v419AddVolumeEntityPacket extends AddVolumeEntityPacket{

	public static function fromLatest(AddVolumeEntityPacket $pk) : self{
		$npk = new self();
		ReflectionUtils::setProperty(AddVolumeEntityPacket::class, $npk, "entityNetId", $pk->getEntityNetId());
		ReflectionUtils::setProperty(AddVolumeEntityPacket::class, $npk, "data", $pk->getData());
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(AddVolumeEntityPacket::class, $this, "entityNetId", $in->getUnsignedVarInt());
		ReflectionUtils::setProperty(AddVolumeEntityPacket::class, $this, "data", new CacheableNbt($in->getNbtCompoundRoot()));
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt($this->getEntityNetId());
		$out->put($this->getData()->getEncodedNbt());
	}

}