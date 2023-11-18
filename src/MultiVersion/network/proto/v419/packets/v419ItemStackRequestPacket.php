<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use MultiVersion\network\proto\v419\packets\types\inventory\stackrequest\v419ItemStackRequest;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;

class v419ItemStackRequestPacket extends ItemStackRequestPacket{

	protected function decodePayload(PacketSerializer $in) : void{
		$requests = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$request = v419ItemStackRequest::read($in);
			$requests[] = new ItemStackRequest($request->getRequestId(), $request->getActions(), $request->getFilterStrings(), $request->getFilterStringCause());
		}
		ReflectionUtils::setProperty(ItemStackRequestPacket::class, $this, "requests", $requests);
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt(count($this->getRequests()));
		foreach($this->getRequests() as $request){
			(new v419ItemStackRequest($request->getRequestId(), $request->getActions(), $request->getFilterStrings(), $request->getFilterStringCause()))->write($out);
		}
	}
}