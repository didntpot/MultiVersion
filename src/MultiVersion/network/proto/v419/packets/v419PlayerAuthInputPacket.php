<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\PlayMode;
use ReflectionException;

class v419PlayerAuthInputPacket extends PlayerAuthInputPacket{

	/**
	 * @throws ReflectionException
	 */
	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "pitch", $in->getLFloat());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "yaw", $in->getLFloat());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "position", $in->getVector3());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "moveVecX", $in->getLFloat());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "moveVecZ", $in->getLFloat());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "headYaw", $in->getLFloat());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "inputFlags", $in->getUnsignedVarLong());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "inputMode", $in->getUnsignedVarInt());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "playMode", $in->getUnsignedVarInt());
		if(ReflectionUtils::getProperty(PlayerAuthInputPacket::class, $this, "playMode") === PlayMode::VR){
			ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "vrGazeDirection", $in->getVector3());
		}
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "tick", $in->getUnsignedVarLong());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "delta", $in->getVector3());
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "analogMoveVecX", 0);
		ReflectionUtils::setProperty(PlayerAuthInputPacket::class, $this, "analogMoveVecZ", 0);
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putLFloat($this->getPitch());
		$out->putLFloat($this->getYaw());
		$out->putVector3($this->getPosition());
		$out->putLFloat($this->getMoveVecX());
		$out->putLFloat($this->getMoveVecZ());
		$out->putLFloat($this->getHeadYaw());
		$out->putUnsignedVarLong($this->getInputFlags());
		$out->putUnsignedVarInt($this->getInputMode());
		$out->putUnsignedVarInt($this->getPlayMode());
		if($this->getPlayMode() === PlayMode::VR){
			assert($this->getVrGazeDirection() !== null);
			$out->putVector3($this->getVrGazeDirection());
		}
		$out->putUnsignedVarLong($this->getTick());
		$out->putVector3($this->getDelta());
	}
}
