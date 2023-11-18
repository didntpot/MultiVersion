<?php

namespace MultiVersion\network\proto\v419\packets\types\inventory\stackresponse;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseContainerInfo;
use function count;

final class v419ItemStackResponse{

	public const RESULT_OK = 0;
	public const RESULT_ERROR = 1;
	//TODO: there are a ton more possible result types but we don't need them yet and they are wayyyyyy too many for me
	//to waste my time on right now...

	/**
	 * @param ItemStackResponseContainerInfo[] $containerInfos
	 */
	public function __construct(
		private int $result,
		private int $requestId,
		private array $containerInfos
	){
	}

	public function getResult() : int{ return $this->result; }

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackResponseContainerInfo[] */
	public function getContainerInfos() : array{ return $this->containerInfos; }

	public static function read(PacketSerializer $in) : self{
		$result = $in->getByte();
		$requestId = $in->readGenericTypeNetworkId();
		$containerInfos = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$containerInfo = v419ItemStackResponseContainerInfo::read($in);
			$containerInfos[] = new ItemStackResponseContainerInfo($containerInfo->getContainerId(), $containerInfo->getSlots());
		}
		return new self($result, $requestId, $containerInfos);
	}

	public function write(PacketSerializer $out) : void{
		$out->putByte($this->result);
		$out->writeGenericTypeNetworkId($this->requestId);
		$out->putUnsignedVarInt(count($this->containerInfos));
		foreach($this->containerInfos as $containerInfo){
			(new v419ItemStackResponseContainerInfo($containerInfo->getContainerId(), $containerInfo->getSlots()))->write($out);
		}
	}
}
