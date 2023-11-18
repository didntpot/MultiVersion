<?php

namespace MultiVersion\network;

use MultiVersion\MultiVersion;
use MultiVersion\network\proto\utils\ReflectionUtils;
use pmmp\thread\ThreadSafeArray;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\PthreadsChannelWriter;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\raklib\RakLibPacketSender;
use pocketmine\network\mcpe\StandardEntityEventBroadcaster;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\Server;
use pocketmine\timings\Timings;
use raklib\server\ipc\RakLibToUserThreadMessageReceiver;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use raklib\utils\InternetAddress;
use ReflectionException;

class MVRakLibInterface extends RakLibInterface{

	/**
	 * @throws ReflectionException
	 */
	public function __construct(Server $server, string $ip, int $port, bool $ipV6){
		$typeConverter = TypeConverter::getInstance();
		$packetSerializerContext = new PacketSerializerContext($typeConverter->getItemTypeDictionary());
		$packetBroadcaster = new StandardPacketBroadcaster($server, $packetSerializerContext);
		$entityEventBroadcaster = new StandardEntityEventBroadcaster($packetBroadcaster, $typeConverter);
		parent::__construct($server, $ip, $port, $ipV6, $packetBroadcaster, $entityEventBroadcaster, $packetSerializerContext, $typeConverter);
		$server->getTickSleeper()->removeNotifier(ReflectionUtils::getProperty(RakLibInterface::class, $this, "sleeperNotifierId"));
		$sleeperEntry = $server->getTickSleeper()->addNotifier(function() : void{
			Timings::$connection->startTiming();
			try{
				while(ReflectionUtils::getProperty(RakLibInterface::class, $this, "eventReceiver")->handle($this)) ;
			}finally{
				Timings::$connection->stopTiming();
			}
		});
		ReflectionUtils::setProperty(RakLibInterface::class, $this, "sleeperNotifierId", $sleeperEntry->getNotifierId());
		/** @phpstan-var ThreadSafeArray<int, string> $mainToThreadBuffer */
		$mainToThreadBuffer = new ThreadSafeArray();
		/** @phpstan-var ThreadSafeArray<int, string> $threadToMainBuffer */
		$threadToMainBuffer = new ThreadSafeArray();
		ReflectionUtils::setProperty(RakLibInterface::class, $this, "rakLib", new MVRakLibServer(
			$server->getLogger(),
			$mainToThreadBuffer,
			$threadToMainBuffer,
			new InternetAddress($ip, $port, $ipV6 ? 6 : 4),
			ReflectionUtils::getProperty(RakLibInterface::class, $this, "rakServerId"),
			(int) $server->getConfigGroup()->getProperty("network.max-mtu-size", 1492),
			MultiVersion::getRaknetAcceptor(),
			$sleeperEntry
		));
		ReflectionUtils::setProperty(RakLibInterface::class, $this, "eventReceiver", new RakLibToUserThreadMessageReceiver(
			new PthreadsChannelReader($threadToMainBuffer)
		));
		ReflectionUtils::setProperty(RakLibInterface::class, $this, "interface", new UserToRakLibThreadMessageSender(
			new PthreadsChannelWriter($mainToThreadBuffer)
		));
	}

	/**
	 * @throws ReflectionException
	 */
	public function onClientConnect(int $sessionId, string $address, int $port, int $clientID) : void{
		$session = new MVNetworkSession(
			Server::getInstance(),
			ReflectionUtils::getProperty(RakLibInterface::class, $this, "network")->getSessionManager(),
			PacketPool::getInstance(),
			ReflectionUtils::getProperty(RakLibInterface::class, $this, "packetSerializerContext"),
			new RakLibPacketSender($sessionId, $this),
			ReflectionUtils::getProperty(RakLibInterface::class, $this, "packetBroadcaster"),
			ReflectionUtils::getProperty(RakLibInterface::class, $this, "entityEventBroadcaster"),
			ZlibCompressor::getInstance(),
			ReflectionUtils::getProperty(RakLibInterface::class, $this, "typeConverter"),
			$address,
			$port
		);
		$sessions = ReflectionUtils::getProperty(RakLibInterface::class, $this, "sessions");
		$sessions[$sessionId] = $session;
		ReflectionUtils::setProperty(RakLibInterface::class, $this, "sessions", $sessions);
	}
}