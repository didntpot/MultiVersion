<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use MultiVersion\network\proto\v419\packets\types\inventory\v419InventoryTransactionChangedSlotsHack;
use MultiVersion\network\proto\v419\packets\types\inventory\v419NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\InventoryTransactionChangedSlotsHack;
use pocketmine\network\mcpe\protocol\types\inventory\MismatchTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;

class v419InventoryTransactionPacket extends InventoryTransactionPacket{

	public static function fromLatest(InventoryTransactionPacket $pk) : self{
		$npk = new self();
		$npk->requestId = $pk->requestId;
		$npk->requestChangedSlots = $pk->requestChangedSlots;
		$npk->trData = $pk->trData;
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->requestId = $in->readGenericTypeNetworkId();
		$this->requestChangedSlots = [];
		if($this->requestId !== 0){
			for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
				$requestChangedSlot = v419InventoryTransactionChangedSlotsHack::read($in);
				$this->requestChangedSlots[] = new InventoryTransactionChangedSlotsHack($requestChangedSlot->getContainerId(), $requestChangedSlot->getChangedSlotIndexes());
			}
		}

		$transactionType = $in->getUnsignedVarInt();

		$this->trData = match ($transactionType) {
			NormalTransactionData::ID => new NormalTransactionData(),
			MismatchTransactionData::ID => new MismatchTransactionData(),
			UseItemTransactionData::ID => new UseItemTransactionData(),
			UseItemOnEntityTransactionData::ID => new UseItemOnEntityTransactionData(),
			ReleaseItemTransactionData::ID => new ReleaseItemTransactionData(),
			default => throw new PacketDecodeException("Unknown transaction type $transactionType"),
		};

		$hasItemStackId = $in->getBool();

		$actions = [];
		$actionCount = $in->getUnsignedVarInt();
		for($i = 0; $i < $actionCount; ++$i){
			$actions[] = (new v419NetworkInventoryAction())->read($in, $hasItemStackId);
		}

		ReflectionUtils::setProperty(get_class($this->trData), $this->trData, "actions", $actions);
		ReflectionUtils::invoke(get_class($this->trData), $this->trData, "decodeData", $in);
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->writeGenericTypeNetworkId($this->requestId);
		if($this->requestId !== 0){
			$out->putUnsignedVarInt(count($this->requestChangedSlots));
			foreach($this->requestChangedSlots as $changedSlots){
				(new v419InventoryTransactionChangedSlotsHack($changedSlots->getContainerId(), $changedSlots->getChangedSlotIndexes()))->write($out);
			}
		}

		$out->putUnsignedVarInt($this->trData->getTypeId());

		$this->trData->encode($out);
	}
}
