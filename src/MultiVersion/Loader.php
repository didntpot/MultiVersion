<?php

declare(strict_types=1);

namespace MultiVersion;

use MultiVersion\network\MVRakLibInterface;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\protocol\PacketViolationWarningPacket;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\query\DedicatedQueryNetworkInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use ReflectionException;

final class Loader extends PluginBase{
	use SingletonTrait;

	private const PACKET_VIOLATION_WARNING_TYPE = [
		PacketViolationWarningPacket::TYPE_MALFORMED => "MALFORMED",
	];
	private const PACKET_VIOLATION_WARNING_SEVERITY = [
		PacketViolationWarningPacket::SEVERITY_WARNING => "WARNING",
		PacketViolationWarningPacket::SEVERITY_FINAL_WARNING => "FINAL WARNING",
		PacketViolationWarningPacket::SEVERITY_TERMINATING_CONNECTION => "TERMINATION",
	];

	public static function getResourcesPath() : string{
		return dirname(__DIR__, 2) . "/resources";
	}

	protected function onLoad() : void{
		self::setInstance($this);
	}

	/**
	 * @throws ReflectionException
	 */
	protected function onEnable() : void{
		$server = $this->getServer();

		$regInterface = function(Server $server, bool $ipv6){
			$server->getNetwork()->registerInterface(new MVRakLibInterface($server, $server->getIp(), $server->getPort(), $ipv6));
		};

		($regInterface)($server, false);
		if($server->getConfigGroup()->getConfigBool("enable-ipv6", true)){
			($regInterface)($server, true);
		}

		$server->getPluginManager()->registerEvent(NetworkInterfaceRegisterEvent::class, function(NetworkInterfaceRegisterEvent $event) : void{
			$interface = $event->getInterface();
			if($interface instanceof MVRakLibInterface || (!$interface instanceof RakLibInterface && !$interface instanceof DedicatedQueryNetworkInterface)){
				return;
			}
			$this->getLogger()->debug("Prevented network interface " . get_class($interface) . " from being registered");
			$event->cancel();
		}, EventPriority::NORMAL, $this);

		$server->getPluginManager()->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			$packet = $event->getPacket();
			if($packet instanceof PacketViolationWarningPacket){
				$this->getLogger()->warning("Received " . self::PACKET_VIOLATION_WARNING_TYPE[$packet->getType()] ?? "UNKNOWN [{$packet->getType()}]" . " Packet Violation (" . self::PACKET_VIOLATION_WARNING_SEVERITY[$packet->getSeverity()] . ") from {$event->getOrigin()->getIp()} message: '{$packet->getMessage()}' Packet ID: 0x" . str_pad(dechex($packet->getPacketId()), 2, "0", STR_PAD_LEFT));
			}
		}, EventPriority::NORMAL, $this);
	}
}
