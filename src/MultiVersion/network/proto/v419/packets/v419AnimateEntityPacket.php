<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419AnimateEntityPacket extends AnimateEntityPacket{

	public static function fromLatest(AnimateEntityPacket $pk) : self{
		$npk = new self();
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $npk, "animation", $pk->getAnimation());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $npk, "nextState", $pk->getNextState());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $npk, "stopExpression", $pk->getStopExpression());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $npk, "controller", $pk->getController());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $npk, "blendOutTime", $pk->getBlendOutTime());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $npk, "actorRuntimeIds", $pk->getActorRuntimeIds());
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $this, "animation", $in->getString());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $this, "nextState", $in->getString());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $this, "stopExpression", $in->getString());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $this, "controller", $in->getString());
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $this, "blendOutTime", $in->getLFloat());
		$actorRuntimeIds = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$actorRuntimeIds[] = $in->getActorRuntimeId();
		}
		ReflectionUtils::setProperty(AnimateEntityPacket::class, $this, "actorRuntimeIds", $actorRuntimeIds);
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putString($this->getAnimation());
		$out->putString($this->getNextState());
		$out->putString($this->getStopExpression());
		$out->putString($this->getController());
		$out->putLFloat($this->getBlendOutTime());
		$out->putUnsignedVarInt(count($this->getActorRuntimeIds()));
		foreach($this->getActorRuntimeIds() as $id){
			$out->putActorRuntimeId($id);
		}
	}
}