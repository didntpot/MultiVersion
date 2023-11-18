<?php

namespace MultiVersion\network\proto\v486\packets\types\inventory\stackresponse;

use InvalidArgumentException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseContainerInfo;
use function count;

final class v486ItemStackResponse{

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
		private array $containerInfos = []
	){
		if($this->result !== self::RESULT_OK && count($this->containerInfos) !== 0){
			throw new InvalidArgumentException("Container infos must be empty if rejecting the request");
		}
	}

	public function getResult() : int{ return $this->result; }

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackResponseContainerInfo[] */
	public function getContainerInfos() : array{ return $this->containerInfos; }

	public static function read(PacketSerializer $in) : self{
		$result = $in->getByte();
		$requestId = $in->readGenericTypeNetworkId();
		$containerInfos = [];
		if($result === self::RESULT_OK){
			for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
				$containerInfo = v486ItemStackResponseContainerInfo::read($in);
				$containerInfos[] = new ItemStackResponseContainerInfo($containerInfo->getContainerId(), $containerInfo->getSlots());
			}
		}
		return new self($result, $requestId, $containerInfos);
	}

	public function write(PacketSerializer $out) : void{
		$out->putByte($this->result);
		$out->writeGenericTypeNetworkId($this->requestId);
		if($this->result === self::RESULT_OK){
			$out->putUnsignedVarInt(count($this->containerInfos));
			foreach($this->containerInfos as $containerInfo){
				(new v486ItemStackResponseContainerInfo($containerInfo->getContainerId(), $containerInfo->getSlots()))->write($out);
			}
		}
	}
}
