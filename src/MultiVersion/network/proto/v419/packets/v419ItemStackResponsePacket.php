<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use MultiVersion\network\proto\v419\packets\types\inventory\stackresponse\v419ItemStackResponse;
use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;

class v419ItemStackResponsePacket extends ItemStackResponsePacket{

	public static function fromLatest(ItemStackResponsePacket $pk) : self{
		$npk = new self();
		ReflectionUtils::setProperty(ItemStackResponsePacket::class, $npk, "responses", $pk->getResponses());
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$responses = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$response = v419ItemStackResponse::read($in);
			$responses[] = new ItemStackResponse($response->getResult(), $response->getRequestId(), $response->getContainerInfos());
		}
		ReflectionUtils::setProperty(ItemStackResponsePacket::class, $this, "responses", $responses);
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt(count($this->getResponses()));
		foreach($this->getResponses() as $response){
			(new v419ItemStackResponse($response->getResult(), $response->getRequestId(), $response->getContainerInfos()))->write($out);
		}
	}
}