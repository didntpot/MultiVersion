<?php

namespace MultiVersion\network\proto\v419\packets\types\inventory\stackrequest;

use InvalidArgumentException;
use MultiVersion\network\proto\utils\ReflectionUtils;
use MultiVersion\network\proto\v419\packets\types\inventory\v419ContainerUIIds;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\BeaconPaymentStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftingConsumeInputStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftingCreateSpecificResultStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeOptionalStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CreativeCreateStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingNonImplementedStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DestroyStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DropStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\GrindstoneStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestActionType;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestSlotInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\LabTableCombineStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\LoomStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\MineBlockStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\PlaceIntoBundleStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\PlaceStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\SwapStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\TakeFromBundleStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\TakeStackRequestAction;
use pocketmine\utils\BinaryDataException;
use ReflectionException;
use function count;

final class v419ItemStackRequest{
	/**
	 * @param ItemStackRequestAction[] $actions
	 * @param string[]                 $filterStrings
	 *
	 * @phpstan-param list<string>     $filterStrings
	 */
	public function __construct(
		private int $requestId,
		private array $actions,
		private array $filterStrings,
		private int $filterStringCause
	){
	}

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackRequestAction[] */
	public function getActions() : array{ return $this->actions; }

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getFilterStrings() : array{ return $this->filterStrings; }

	public function getFilterStringCause() : int{ return $this->filterStringCause; }

	/**
	 * @throws BinaryDataException
	 * @throws PacketDecodeException
	 * @throws ReflectionException
	 */
	private static function readAction(PacketSerializer $in, int $typeId) : ItemStackRequestAction{
		$action = match ($typeId) {
			TakeStackRequestAction::ID => TakeStackRequestAction::read($in),
			PlaceStackRequestAction::ID => PlaceStackRequestAction::read($in),
			SwapStackRequestAction::ID => SwapStackRequestAction::read($in),
			DropStackRequestAction::ID => DropStackRequestAction::read($in),
			DestroyStackRequestAction::ID => DestroyStackRequestAction::read($in),
			CraftingConsumeInputStackRequestAction::ID => CraftingConsumeInputStackRequestAction::read($in),
			CraftingCreateSpecificResultStackRequestAction::ID => CraftingCreateSpecificResultStackRequestAction::read($in),
			PlaceIntoBundleStackRequestAction::ID => PlaceIntoBundleStackRequestAction::read($in),
			TakeFromBundleStackRequestAction::ID => TakeFromBundleStackRequestAction::read($in),
			LabTableCombineStackRequestAction::ID => LabTableCombineStackRequestAction::read($in),
			BeaconPaymentStackRequestAction::ID => BeaconPaymentStackRequestAction::read($in),
			MineBlockStackRequestAction::ID => MineBlockStackRequestAction::read($in),
			CraftRecipeStackRequestAction::ID => CraftRecipeStackRequestAction::read($in),
			CraftRecipeAutoStackRequestAction::ID => v419CraftRecipeAutoStackRequestAction::read($in),
			CreativeCreateStackRequestAction::ID => CreativeCreateStackRequestAction::read($in),
			CraftRecipeOptionalStackRequestAction::ID => CraftRecipeOptionalStackRequestAction::read($in),
			GrindstoneStackRequestAction::ID => GrindstoneStackRequestAction::read($in),
			LoomStackRequestAction::ID => LoomStackRequestAction::read($in),
			DeprecatedCraftingNonImplementedStackRequestAction::ID => DeprecatedCraftingNonImplementedStackRequestAction::read($in),
			DeprecatedCraftingResultsStackRequestAction::ID => DeprecatedCraftingResultsStackRequestAction::read($in),
			default => throw new PacketDecodeException("Unhandled item stack request action type $typeId"),
		};
		if($action instanceof SwapStackRequestAction){
			if(($containerId = ($slot1 = $action->getSlot1())->getContainerId()) >= v419ContainerUIIds::RECIPE_BOOK){
				$containerId++;
				ReflectionUtils::setProperty(get_class($action), $action, "slot1", new ItemStackRequestSlotInfo($containerId, $slot1->getSlotId(), $slot1->getStackId()));
			}
			if(($containerId = ($slot2 = $action->getSlot2())->getContainerId()) >= v419ContainerUIIds::RECIPE_BOOK){
				$containerId++;
				ReflectionUtils::setProperty(get_class($action), $action, "slot2", new ItemStackRequestSlotInfo($containerId, $slot2->getSlotId(), $slot2->getStackId()));
			}
		}elseif($action instanceof CraftingConsumeInputStackRequestAction |
			$action instanceof DestroyStackRequestAction |
			$action instanceof DropStackRequestAction
		){
			if(($containerId = ($source = $action->getSource())->getContainerId()) >= v419ContainerUIIds::RECIPE_BOOK){
				$containerId++;
				ReflectionUtils::setProperty(get_class($action), $action, "source", new ItemStackRequestSlotInfo($containerId, $source->getSlotId(), $source->getStackId()));
			}
		}elseif($action instanceof PlaceIntoBundleStackRequestAction |
			$action instanceof PlaceStackRequestAction |
			$action instanceof TakeFromBundleStackRequestAction |
			$action instanceof TakeStackRequestAction
		){
			if(($containerId = ($source = $action->getSource())->getContainerId()) >= v419ContainerUIIds::RECIPE_BOOK){
				$containerId++;
				ReflectionUtils::setProperty(get_class($action), $action, "source", new ItemStackRequestSlotInfo($containerId, $source->getSlotId(), $source->getStackId()));
			}
			if(($containerId = ($destination = $action->getDestination())->getContainerId()) >= v419ContainerUIIds::RECIPE_BOOK){
				$containerId++;
				ReflectionUtils::setProperty(get_class($action), $action, "destination", new ItemStackRequestSlotInfo($containerId, $destination->getSlotId(), $destination->getStackId()));
			}
		}elseif($action instanceof v419CraftRecipeAutoStackRequestAction){
			$action = new CraftRecipeAutoStackRequestAction($action->getRecipeId(), $action->getRepetitions(), $action->getIngredients());
		}
		return $action;
	}

	public static function read(PacketSerializer $in) : self{
		$requestId = $in->readGenericTypeNetworkId();
		$actions = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$typeId = $in->getByte();
			if($typeId >= ItemStackRequestActionType::PLACE_INTO_BUNDLE){
				$typeId += ItemStackRequestActionType::LAB_TABLE_COMBINE - ItemStackRequestActionType::PLACE_INTO_BUNDLE;
			}
			$actions[] = self::readAction($in, $typeId);
		}
		$filterStrings = [];
		$filterStringCause = 0;
		return new self($requestId, $actions, $filterStrings, $filterStringCause);
	}

	/**
	 * @throws ReflectionException
	 */
	private static function writeAction(PacketSerializer $out, ItemStackRequestAction $action) : void{
		if($action instanceof SwapStackRequestAction){
			if(($containerId = ($slot1 = $action->getSlot1())->getContainerId()) > v419ContainerUIIds::RECIPE_BOOK){
				$containerId--;
				ReflectionUtils::setProperty(get_class($action), $action, "slot1", new ItemStackRequestSlotInfo($containerId, $slot1->getSlotId(), $slot1->getStackId()));
			}elseif($containerId === v419ContainerUIIds::RECIPE_BOOK){
				throw new InvalidArgumentException("Invalid container ID for protocol version 419");
			}
			if(($containerId = ($slot2 = $action->getSlot2())->getContainerId()) > v419ContainerUIIds::RECIPE_BOOK){
				$containerId--;
				ReflectionUtils::setProperty(get_class($action), $action, "slot2", new ItemStackRequestSlotInfo($containerId, $slot2->getSlotId(), $slot2->getStackId()));
			}elseif($containerId === v419ContainerUIIds::RECIPE_BOOK){
				throw new InvalidArgumentException("Invalid container ID for protocol version 419");
			}
		}elseif($action instanceof CraftingConsumeInputStackRequestAction |
			$action instanceof DestroyStackRequestAction |
			$action instanceof DropStackRequestAction
		){
			if(($containerId = ($source = $action->getSource())->getContainerId()) > v419ContainerUIIds::RECIPE_BOOK){
				$containerId--;
				ReflectionUtils::setProperty(get_class($action), $action, "source", new ItemStackRequestSlotInfo($containerId, $source->getSlotId(), $source->getStackId()));
			}elseif($containerId === v419ContainerUIIds::RECIPE_BOOK){
				throw new InvalidArgumentException("Invalid container ID for protocol version 419");
			}
		}elseif($action instanceof PlaceIntoBundleStackRequestAction |
			$action instanceof PlaceStackRequestAction |
			$action instanceof TakeFromBundleStackRequestAction |
			$action instanceof TakeStackRequestAction
		){
			if(($containerId = ($source = $action->getSource())->getContainerId()) > v419ContainerUIIds::RECIPE_BOOK){
				$containerId--;
				ReflectionUtils::setProperty(get_class($action), $action, "source", new ItemStackRequestSlotInfo($containerId, $source->getSlotId(), $source->getStackId()));
			}elseif($containerId === v419ContainerUIIds::RECIPE_BOOK){
				throw new InvalidArgumentException("Invalid container ID for protocol version 419");
			}
			if(($containerId = ($destination = $action->getDestination())->getContainerId()) > v419ContainerUIIds::RECIPE_BOOK){
				$containerId--;
				ReflectionUtils::setProperty(get_class($action), $action, "destination", new ItemStackRequestSlotInfo($containerId, $destination->getSlotId(), $destination->getStackId()));
			}elseif($containerId === v419ContainerUIIds::RECIPE_BOOK){
				throw new InvalidArgumentException("Invalid container ID for protocol version 419");
			}
		}elseif($action instanceof v419CraftRecipeAutoStackRequestAction){
			$action = new CraftRecipeAutoStackRequestAction($action->getRecipeId(), $action->getRepetitions(), $action->getIngredients());
		}
		$action->write($out);
	}

	/**
	 * @throws ReflectionException
	 */
	public function write(PacketSerializer $out) : void{
		$out->writeGenericTypeNetworkId($this->requestId);
		$out->putUnsignedVarInt(count($this->actions));
		foreach($this->actions as $action){
			$typeId = $action->getTypeId();
			if($typeId >= ItemStackRequestActionType::PLACE_INTO_BUNDLE){
				$typeId -= ItemStackRequestActionType::LAB_TABLE_COMBINE - ItemStackRequestActionType::PLACE_INTO_BUNDLE;
			}
			$out->putByte($typeId);
			self::writeAction($out, $action);
		}
	}
}
