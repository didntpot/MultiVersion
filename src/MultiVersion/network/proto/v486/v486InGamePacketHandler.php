<?php

namespace MultiVersion\network\proto\v486;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\entity\Attribute;
use pocketmine\inventory\transaction\action\CreateItemAction;
use pocketmine\inventory\transaction\action\DestroyItemAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\CraftingTransaction;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\inventory\transaction\TransactionException;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConversionException;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\MismatchTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use ReflectionException;

class v486InGamePacketHandler extends InGamePacketHandler{

	/** @var CraftingTransaction|null */
	protected ?CraftingTransaction $craftingTransaction = null;

	/** @var float */
	protected float $lastRightClickTime = 0.0;
	/** @var UseItemTransactionData|null */
	protected ?UseItemTransactionData $lastRightClickData = null;


	/**
	 * @throws ReflectionException
	 */
	public function handleInventoryTransaction(InventoryTransactionPacket $packet) : bool{
		$result = true;

		if(count($packet->trData->getActions()) > 100){
			throw new PacketHandlingException("Too many actions in inventory transaction");
		}

		$this->addRawPredictedSlotChanges($packet->trData->getActions());

		if($packet->trData instanceof NormalTransactionData){
			$result = $this->handleNormalTransaction($packet->trData);
		}elseif($packet->trData instanceof MismatchTransactionData){
			$this->getSession()->getLogger()->debug("Mismatch transaction received");
			$this->getSession()->getInvManager()->syncAll();
			$result = true;
		}elseif($packet->trData instanceof UseItemTransactionData){
			$result = $this->handleUseItemTransaction($packet->trData);
		}elseif($packet->trData instanceof UseItemOnEntityTransactionData){
			$result = $this->handleUseItemOnEntityTransaction($packet->trData);
		}elseif($packet->trData instanceof ReleaseItemTransactionData){
			$result = $this->handleReleaseItemTransaction($packet->trData);
		}

		if($this->craftingTransaction === null){ //don't sync if we're waiting to complete a crafting transaction
			$this->getSession()->getInvManager()->syncMismatchedPredictedSlotChanges();
		}
		return $result;
	}

	/**
	 * @throws ReflectionException
	 */
	private function handleNormalTransaction(NormalTransactionData $data) : bool{
		/** @var InventoryAction[] $actions */
		$actions = [];

		$isCraftingPart = false;
		foreach($data->getActions() as $networkInventoryAction){
			if(
				$networkInventoryAction->sourceType === NetworkInventoryAction::SOURCE_TODO || (
					$this->craftingTransaction !== null &&
					!$networkInventoryAction->oldItem->getItemStack()->equals($networkInventoryAction->newItem->getItemStack()) &&
					$networkInventoryAction->sourceType === NetworkInventoryAction::SOURCE_CONTAINER &&
					$networkInventoryAction->windowId === ContainerIds::UI &&
					$networkInventoryAction->inventorySlot === UIInventorySlotOffset::CREATED_ITEM_OUTPUT
				)
			){
				$isCraftingPart = true;
			}

			try{
				$action = $this->createInventoryAction($networkInventoryAction, $this->getPlayer(), $this->getSession()->getInvManager());
				if($action !== null){
					$actions[] = $action;
				}
			}catch(TypeConversionException $e){
				$this->getSession()->getLogger()->debug("Error unpacking inventory action: " . $e->getMessage());
				return false;
			}
		}

		if($isCraftingPart){
			if($this->craftingTransaction === null){
				//TODO: this might not be crafting if there is a special inventory open (anvil, enchanting, loom etc)
				$this->craftingTransaction = new CraftingTransaction($this->getPlayer(), $this->getPlayer()->getServer()->getCraftingManager(), $actions);
			}else{
				foreach($actions as $action){
					$this->craftingTransaction->addAction($action);
				}
			}

			try{
				$this->craftingTransaction->validate();
			}catch(TransactionValidationException $e){
				//transaction is incomplete - crafting transaction comes in lots of little bits, so we have to collect
				//all of the parts before we can execute it
				return true;
			}
			$this->getPlayer()->setUsingItem(false);
			try{
				$this->craftingTransaction->execute();
			}catch(TransactionException $e){
				$this->getSession()->getLogger()->debug("Failed to execute crafting transaction: " . $e->getMessage());
				return false;
			}finally{
				$this->craftingTransaction = null;
			}
		}else{
			//normal transaction fallthru
			if($this->craftingTransaction !== null){
				$this->getSession()->getLogger()->debug("Got unexpected normal inventory action with incomplete crafting transaction, refusing to execute crafting");
				$this->craftingTransaction = null;
				return false;
			}

			if(count($actions) === 0){
				//TODO: 1.13+ often sends transactions with nothing but useless crap in them, no need for the debug noise
				return true;
			}

			$this->getPlayer()->setUsingItem(false);
			$transaction = new InventoryTransaction($this->getPlayer(), $actions);
			try{
				$transaction->execute();
			}catch(TransactionException $e){
				$logger = $this->getSession()->getLogger();
				$logger->debug("Failed to execute inventory transaction: " . $e->getMessage());
				$logger->debug("Actions: " . json_encode($data->getActions()));
				return false;
			}
		}

		return true;
	}

	/**
	 * @throws ReflectionException
	 */
	private function handleUseItemTransaction(UseItemTransactionData $data) : bool{
		$this->getPlayer()->selectHotbarSlot($data->getHotbarSlot());

		switch($data->getActionType()){
			case UseItemTransactionData::ACTION_CLICK_BLOCK:
				//TODO: start hack for client spam bug
				$clickPos = $data->getClickPosition();
				$spamBug = ($this->lastRightClickData !== null &&
					microtime(true) - $this->lastRightClickTime < 0.1 && //100ms
					$this->lastRightClickData->getPlayerPosition()->distanceSquared($data->getPlayerPosition()) < 0.00001 &&
					$this->lastRightClickData->getBlockPosition()->equals($data->getBlockPosition()) &&
					$this->lastRightClickData->getClickPosition()->distanceSquared($clickPos) < 0.00001 //signature spam bug has 0 distance, but allow some error
				);
				//get rid of continued spam if the player clicks and holds right-click
				$this->lastRightClickData = $data;
				$this->lastRightClickTime = microtime(true);
				if($spamBug){
					return true;
				}
				//TODO: end hack for client spam bug

				self::validateFacing($data->getFace());

				$blockPos = $data->getBlockPosition();
				$vBlockPos = new Vector3($blockPos->getX(), $blockPos->getY(), $blockPos->getZ());
				if(!$this->getPlayer()->interactBlock($vBlockPos, $data->getFace(), $clickPos)){
					$this->onFailedBlockAction($vBlockPos, $data->getFace());
				}
				return true;
			case UseItemTransactionData::ACTION_BREAK_BLOCK:
				$blockPos = $data->getBlockPosition();
				$vBlockPos = new Vector3($blockPos->getX(), $blockPos->getY(), $blockPos->getZ());
				if(!$this->getPlayer()->breakBlock($vBlockPos)){
					$this->onFailedBlockAction($vBlockPos, null);
				}
				return true;
			case UseItemTransactionData::ACTION_CLICK_AIR:
				if($this->getPlayer()->isUsingItem()){
					if(!$this->getPlayer()->consumeHeldItem()){
						$hungerAttr = $this->getPlayer()->getAttributeMap()->get(Attribute::HUNGER) ?? throw new AssumptionFailedError();
						$hungerAttr->markSynchronized(false);
					}
					return true;
				}
				$this->getPlayer()->useHeldItem();
				return true;
		}

		return false;
	}

	/**
	 * @throws PacketHandlingException
	 */
	private static function validateFacing(int $facing) : void{
		if(!in_array($facing, Facing::ALL, true)){
			throw new PacketHandlingException("Invalid facing value $facing");
		}
	}

	/**
	 * Internal function used to execute rollbacks when an action fails on a block.
	 * @throws ReflectionException
	 */
	private function onFailedBlockAction(Vector3 $blockPos, ?int $face) : void{
		if($blockPos->distanceSquared($this->getPlayer()->getLocation()) < 10000){
			$blocks = $blockPos->sidesArray();
			if($face !== null){
				$sidePos = $blockPos->getSide($face);

				/** @var Vector3[] $blocks */
				array_push($blocks, ...$sidePos->sidesArray()); //getAllSides() on each of these will include $blockPos and $sidePos because they are next to each other
			}else{
				$blocks[] = $blockPos;
			}
			foreach($this->getPlayer()->getWorld()->createBlockUpdatePackets($blocks) as $packet){
				$this->getSession()->sendDataPacket($packet);
			}
		}
	}

	/**
	 * @throws ReflectionException
	 */
	private function handleUseItemOnEntityTransaction(UseItemOnEntityTransactionData $data) : bool{
		$target = $this->getPlayer()->getWorld()->getEntity($data->getActorRuntimeId());
		if($target === null){
			return false;
		}

		$this->getPlayer()->selectHotbarSlot($data->getHotbarSlot());

		switch($data->getActionType()){
			case UseItemOnEntityTransactionData::ACTION_INTERACT:
				$this->getPlayer()->interactEntity($target, $data->getClickPosition());
				return true;
			case UseItemOnEntityTransactionData::ACTION_ATTACK:
				$this->getPlayer()->attackEntity($target);
				return true;
		}

		return false;
	}

	private function handleReleaseItemTransaction(ReleaseItemTransactionData $data) : bool{
		$this->getPlayer()->selectHotbarSlot($data->getHotbarSlot());

		if($data->getActionType() == ReleaseItemTransactionData::ACTION_RELEASE){
			$this->getPlayer()->releaseHeldItem();
			return true;
		}

		return false;
	}

	/**
	 * @throws TypeConversionException
	 */
	public function createInventoryAction(NetworkInventoryAction $action, Player $player, InventoryManager $inventoryManager) : ?InventoryAction{
		if($action->oldItem->getItemStack()->equals($action->newItem->getItemStack())){
			//filter out useless noise in 1.13
			return null;
		}
		$converter = TypeConverter::getInstance();
		try{
			$old = $converter->netItemStackToCore($action->oldItem->getItemStack());
		}catch(TypeConversionException $e){
			throw TypeConversionException::wrap($e, 'Inventory action: oldItem');
		}
		try{
			$new = $converter->netItemStackToCore($action->newItem->getItemStack());
		}catch(TypeConversionException $e){
			throw TypeConversionException::wrap($e, 'Inventory action: newItem');
		}
		switch($action->sourceType){
			case NetworkInventoryAction::SOURCE_CONTAINER:
				if($action->windowId === ContainerIds::UI && $action->inventorySlot === UIInventorySlotOffset::CREATED_ITEM_OUTPUT){
					return null; //useless noise
				}
				$located = $inventoryManager->locateWindowAndSlot($action->windowId, $action->inventorySlot);
				if($located !== null){
					[$window, $slot] = $located;
					return new SlotChangeAction($window, $slot, $old, $new);
				}

				throw new TypeConversionException("No open container with window ID $action->windowId");
			case NetworkInventoryAction::SOURCE_WORLD:
				if($action->inventorySlot !== NetworkInventoryAction::ACTION_MAGIC_SLOT_DROP_ITEM){
					throw new TypeConversionException('Only expecting drop-item world actions from the client!');
				}

				return new DropItemAction($new);
			case NetworkInventoryAction::SOURCE_CREATIVE:
				switch($action->inventorySlot){
					case NetworkInventoryAction::ACTION_MAGIC_SLOT_CREATIVE_DELETE_ITEM:
						return new DestroyItemAction($new);
					case NetworkInventoryAction::ACTION_MAGIC_SLOT_CREATIVE_CREATE_ITEM:
						return new CreateItemAction($old);
					default:
						throw new TypeConversionException("Unexpected creative action type $action->inventorySlot");

				}
			case NetworkInventoryAction::SOURCE_TODO:
				//These are used to balance a transaction that involves special actions, like crafting, enchanting, etc.
				//The vanilla server just accepted these without verifying them. We don't need to care about them since
				//we verify crafting by checking for imbalances anyway.
				return null;
			default:
				throw new TypeConversionException("Unknown inventory source type $action->sourceType");
		}
	}

	/**
	 * @param NetworkInventoryAction[] $networkInventoryActions
	 *
	 * @throws PacketHandlingException|ReflectionException
	 */
	public function addRawPredictedSlotChanges(array $networkInventoryActions) : void{
		$invManager = $this->getSession()->getInvManager();
		foreach($networkInventoryActions as $action){
			if($action->sourceType !== NetworkInventoryAction::SOURCE_CONTAINER){
				continue;
			}

			//legacy transactions should not modify or predict anything other than these inventories, since these are
			//the only ones accessible when not in-game (ItemStackRequest is used for everything else)
			if(match ($action->windowId) {
				ContainerIds::INVENTORY, ContainerIds::OFFHAND, ContainerIds::ARMOR, ContainerIds::UI => false,
				default => true
			}){
				throw new PacketHandlingException("Legacy transactions cannot predict changes to inventory with ID " . $action->windowId);
			}
			$info = $invManager->locateWindowAndSlot($action->windowId, $action->inventorySlot);
			if($info === null){
				continue;
			}

			[$inventory, $slot] = $info;
			ReflectionUtils::invoke(InventoryManager::class, $invManager, "addPredictedSlotChange", $inventory, $slot, $action->newItem->getItemStack());
		}
	}

	/**
	 * @throws ReflectionException
	 */
	private function getSession() : NetworkSession{
		return ReflectionUtils::getProperty(InGamePacketHandler::class, $this, "session");
	}

	/**
	 * @throws ReflectionException
	 */
	private function getPlayer() : Player{
		return ReflectionUtils::getProperty(InGamePacketHandler::class, $this, "player");
	}
}