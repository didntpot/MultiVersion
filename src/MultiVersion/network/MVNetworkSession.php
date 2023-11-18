<?php

namespace MultiVersion\network;

use Closure;
use InvalidArgumentException;
use MultiVersion\network\proto\chunk\MVChunkCache;
use MultiVersion\network\proto\MVLoginPacketHandler;
use MultiVersion\network\proto\PacketTranslator;
use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\event\server\DataPacketDecodeEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\DecompressionException;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\encryption\DecryptionException;
use pocketmine\network\mcpe\encryption\EncryptionContext;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\handler\SessionStartPacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketRateLimiter;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\NetworkSessionManager;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\PlayerInfo;
use pocketmine\player\UsedChunkStatus;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use ReflectionException;

class MVNetworkSession extends NetworkSession{

	private const INCOMING_PACKET_BATCH_PER_TICK = 2;
	private const INCOMING_PACKET_BATCH_BUFFER_TICKS = 100;

	private const INCOMING_GAME_PACKETS_PER_TICK = 2;
	private const INCOMING_GAME_PACKETS_BUFFER_TICKS = 100;

	private PacketRateLimiter $packetBatchLimiter;
	private PacketRateLimiter $gamePacketLimiter;

	private PacketTranslator $pkTranslator;

	private bool $enableCompression = true;

	private bool $isFirstPacket = true;

	public function __construct(Server $server, NetworkSessionManager $manager, PacketPool $packetPool, PacketSerializerContext $packetSerializerContext, PacketSender $sender, PacketBroadcaster $broadcaster, EntityEventBroadcaster $entityEventBroadcaster, Compressor $compressor, TypeConverter $typeConverter, string $ip, int $port){
		$this->packetBatchLimiter = new PacketRateLimiter("Packet Batches", self::INCOMING_PACKET_BATCH_PER_TICK, self::INCOMING_PACKET_BATCH_BUFFER_TICKS, 5_000_000);
		$this->gamePacketLimiter = new PacketRateLimiter("Game Packets", self::INCOMING_GAME_PACKETS_PER_TICK, self::INCOMING_GAME_PACKETS_BUFFER_TICKS, 5_000_000);
		parent::__construct($server, $manager, $packetPool, $packetSerializerContext, $sender, $broadcaster, $entityEventBroadcaster, $compressor, $typeConverter, $ip, $port);
		$this->setHandler(new MVLoginPacketHandler(
			Server::getInstance(),
			$this,
			function(PlayerInfo $info) : void{
				ReflectionUtils::setProperty(NetworkSession::class, $this, "info", $info);
				$this->getLogger()->info(Server::getInstance()->getLanguage()->translate(KnownTranslationFactory::pocketmine_network_session_playerName(TextFormat::AQUA . $info->getUsername() . TextFormat::RESET)));
				$this->getLogger()->setPrefix("NetworkSession: " . $this->getDisplayName());
				ReflectionUtils::getProperty(NetworkSession::class, $this, "manager")->markLoginReceived($this);
			},
			function(bool $authenticated, bool $authRequired, Translatable|string|null $error, ?string $clientPubKey) : void{
				ReflectionUtils::invoke(NetworkSession::class, $this, "setAuthenticationStatus", $authenticated, $authRequired, $error, $clientPubKey);
			},
		));
	}

	/**
	 * @throws ReflectionException
	 */
	private function onSessionStartSuccess() : void{
		$this->getLogger()->debug("Session start handshake completed, awaiting login packet");
		$this->flushSendBuffer(true);
		$this->enableCompression = true;
		$this->setHandler(new MVLoginPacketHandler(
			Server::getInstance(),
			$this,
			function(PlayerInfo $info) : void{
				ReflectionUtils::setProperty(NetworkSession::class, $this, "info", $info);
				$this->getLogger()->info(Server::getInstance()->getLanguage()->translate(KnownTranslationFactory::pocketmine_network_session_playerName(TextFormat::AQUA . $info->getUsername() . TextFormat::RESET)));
				$this->getLogger()->setPrefix("NetworkSession: " . $this->getDisplayName());
				ReflectionUtils::getProperty(NetworkSession::class, $this, "manager")->markLoginReceived($this);
			},
			function(bool $authenticated, bool $authRequired, Translatable|string|null $error, ?string $clientPubKey) : void{
				ReflectionUtils::invoke(NetworkSession::class, $this, "setAuthenticationStatus", $authenticated, $authRequired, $error, $clientPubKey);
			},
		));
	}

	/**
	 * @throws ReflectionException
	 */
	public function setPacketTranslator(PacketTranslator $pkTranslator) : void{
		$this->pkTranslator = $pkTranslator;
		EncryptionContext::$ENABLED = $pkTranslator::ENCRYPTION_CONTEXT;
		ReflectionUtils::setProperty(NetworkSession::class, $this, "packetPool", $this->pkTranslator->getPacketPool());
		ReflectionUtils::setProperty(NetworkSession::class, $this, "packetSerializerContext", $this->pkTranslator->getPacketSerializerContext());
		ReflectionUtils::setProperty(NetworkSession::class, $this, "broadcaster", $this->pkTranslator->getBroadcaster());
		ReflectionUtils::setProperty(NetworkSession::class, $this, "entityEventBroadcaster", $this->pkTranslator->getEntityEventBroadcaster());
		ReflectionUtils::setProperty(NetworkSession::class, $this, "compressor", $this->pkTranslator->getCompressor());
		ReflectionUtils::setProperty(NetworkSession::class, $this, "typeConverter", $this->pkTranslator->getTypeConverter());
	}

	public function getPacketTranslator() : PacketTranslator{
		return $this->pkTranslator;
	}

	/**
	 * @throws ReflectionException
	 */
	public function handleEncoded(string $payload) : void{
		if(!ReflectionUtils::getProperty(NetworkSession::class, $this, "connected")){
			return;
		}

		Timings::$playerNetworkReceive->startTiming();
		try{
			$this->packetBatchLimiter->decrement();

			$cipher = ReflectionUtils::getProperty(NetworkSession::class, $this, "cipher");
			if($cipher !== null){
				Timings::$playerNetworkReceiveDecrypt->startTiming();
				try{
					$payload = $cipher->decrypt($payload);
				}catch(DecryptionException $e){
					$this->getLogger()->debug("Encrypted packet: " . base64_encode($payload));
					throw PacketHandlingException::wrap($e, "Packet decryption error");
				}finally{
					Timings::$playerNetworkReceiveDecrypt->stopTiming();
				}
			}

			if($this->enableCompression){
				Timings::$playerNetworkReceiveDecompress->startTiming();
				try{
					$decompressed = $this->getCompressor()->decompress($payload);
				}catch(DecompressionException $e){
					if($this->isFirstPacket){
						$this->getLogger()->debug("Failed to decompress packet: " . base64_encode($payload));

						$this->enableCompression = false;
						$this->setHandler(new SessionStartPacketHandler(
							$this,
							fn() => $this->onSessionStartSuccess()
						));

						$decompressed = $payload;
					}else{
						$this->getLogger()->debug("Failed to decompress packet: " . base64_encode($payload));
						throw PacketHandlingException::wrap($e, "Compressed packet batch decode error");
					}
				}finally{
					Timings::$playerNetworkReceiveDecompress->stopTiming();
				}
			}else{
				$decompressed = $payload;
			}

			try{
				$stream = new BinaryStream($decompressed);
				$count = 0;
				foreach(PacketBatch::decodeRaw($stream) as $buffer){
					$this->gamePacketLimiter->decrement();
					if(++$count > 1300){
						throw new PacketHandlingException("Too many packets in batch");
					}
					$packet = ReflectionUtils::getProperty(NetworkSession::class, $this, "packetPool")->getPacket($buffer);
					if($packet === null){
						$this->getLogger()->debug("Unknown packet: " . base64_encode($buffer));
						throw new PacketHandlingException("Unknown packet received");
					}
					try{
						$this->handleDataPacket($packet, $buffer);
					}catch(PacketHandlingException $e){
						$this->getLogger()->debug($packet->getName() . ": " . base64_encode($buffer));
						throw PacketHandlingException::wrap($e, "Error processing " . $packet->getName());
					}
				}
			}catch(PacketDecodeException|BinaryDataException $e){
				$this->getLogger()->logException($e);
				throw PacketHandlingException::wrap($e, "Packet batch decode error");
			}finally{
				$this->isFirstPacket = false;
			}
		}finally{
			Timings::$playerNetworkReceive->stopTiming();
		}
	}

	public function handleDataPacket(Packet $packet, string $buffer) : void{
		if(!isset($this->pkTranslator)){
			parent::handleDataPacket($packet, $buffer);
			return;
		}

		if(!$packet instanceof ServerboundPacket){
			throw new PacketDecodeException("Unexpected non-serverbound packet");
		}

		$timings = Timings::getReceiveDataPacketTimings($packet);
		$timings->startTiming();

		try{
			$ev = new DataPacketDecodeEvent($this, $packet->pid(), $buffer);
			$ev->call();
			if($ev->isCancelled()){
				return;
			}

			$decodeTimings = Timings::getDecodeDataPacketTimings($packet);
			$decodeTimings->startTiming();
			try{
				$stream = $this->getPacketTranslator()->getPacketSerializerFactory()->newDecoder($buffer, 0, $this->getPacketTranslator()->getPacketSerializerFactory()->newSerializerContext());
				try{
					$packet->decode($stream);
				}catch(PacketDecodeException $e){
					throw PacketHandlingException::wrap($e);
				}
				if(!$stream->feof()){
					$remains = substr($stream->getBuffer(), $stream->getOffset());
					$this->getLogger()->debug("Still " . strlen($remains) . " bytes unread in " . $packet->getName() . ": " . bin2hex($remains));
				}
			}finally{
				$decodeTimings->stopTiming();
			}

			$packet = $this->getPacketTranslator()->handleIncoming(clone $packet);
			if($packet === null){
				return;
			}

			$ev = new DataPacketReceiveEvent($this, $packet);
			$ev->call();
			if(!$ev->isCancelled()){
				$handlerTimings = Timings::getHandleDataPacketTimings($packet);
				$handlerTimings->startTiming();
				try{
					if($this->getHandler() === null || !$packet->handle($this->getHandler())){
						$this->getLogger()->debug("Unhandled " . $packet->getName() . ": " . base64_encode($stream->getBuffer()));
					}
				}finally{
					$handlerTimings->stopTiming();
				}
			}
		}finally{
			$timings->stopTiming();
		}
	}

	/**
	 * @throws ReflectionException
	 */
	public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false) : bool{
		if(!isset($this->pkTranslator)){
			return parent::sendDataPacket($packet, $immediate);
		}

		if(!ReflectionUtils::getProperty(NetworkSession::class, $this, "connected")){
			return false;
		}

		//Basic safety restriction. TODO: improve this
		if(!ReflectionUtils::getProperty(NetworkSession::class, $this, "loggedIn") and !$packet->canBeSentBeforeLogin()){
			throw new InvalidArgumentException("Attempted to send " . get_class($packet) . " to " . $this->getDisplayName() . " too early");
		}

		$timings = Timings::getSendDataPacketTimings($packet);
		$timings->startTiming();
		try{
			$ev = new DataPacketSendEvent([$this], [$packet]);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}

			$packets = $ev->getPackets();

			foreach($packets as $packet){
				$pk = $this->getPacketTranslator()->handleOutgoing(clone $packet);
				if($pk === null){
					continue;
				}
				$this->addToSendBuffer(self::encodePacketTimed($this->getPacketTranslator()->getPacketSerializerFactory()->newEncoder($this->getPacketTranslator()->getPacketSerializerFactory()->newSerializerContext()), $pk));
			}
			if($immediate){
				$this->flushSendBuffer(true);
			}

			return true;
		}finally{
			$timings->stopTiming();
		}
	}

	/**
	 * @throws ReflectionException
	 */
	private function flushSendBuffer(bool $immediate = false) : void{
		$sendBuffer = ReflectionUtils::getProperty(NetworkSession::class, $this, "sendBuffer");
		if(count($sendBuffer) > 0){
			Timings::$playerNetworkSend->startTiming();
			try{
				$syncMode = null;
				if($immediate){
					$syncMode = true;
				}elseif(ReflectionUtils::getProperty(NetworkSession::class, $this, "forceAsyncCompression")){
					$syncMode = false;
				}

				$stream = new BinaryStream();
				PacketBatch::encodeRaw($stream, $sendBuffer);

				if($this->enableCompression){
					$promise = Server::getInstance()->prepareBatch($stream->getBuffer(), $this->getCompressor(), $syncMode, Timings::$playerNetworkSendCompressSessionBuffer);
				}else{
					$promise = new CompressBatchPromise();
					$promise->resolve($stream->getBuffer());
				}

				ReflectionUtils::setProperty(NetworkSession::class, $this, "sendBuffer", []);
				ReflectionUtils::invoke(NetworkSession::class, $this, "queueCompressedNoBufferFlush", $promise, $immediate);
			}finally{
				Timings::$playerNetworkSend->stopTiming();
			}
		}
	}

	/**
	 * Instructs the networksession to start using the chunk at the given coordinates. This may occur asynchronously.
	 *
	 * @param int                      $chunkX
	 * @param int                      $chunkZ
	 * @param Closure                  $onCompletion To be called when chunk sending has completed.
	 *
	 * @phpstan-param Closure() : void $onCompletion
	 */
	public function startUsingChunk(int $chunkX, int $chunkZ, Closure $onCompletion) : void{
		Utils::validateCallableSignature(function() : void{
		}, $onCompletion);

		$world = $this->getPlayer()->getLocation()->getWorld();
		MVChunkCache::getInstance($world, $this->getCompressor(), $this->getPacketTranslator())->request($chunkX, $chunkZ)->onResolve(
		//this callback may be called synchronously or asynchronously, depending on whether the promise is resolved yet
			function(CompressBatchPromise $promise) use ($world, $onCompletion, $chunkX, $chunkZ) : void{
				if(!$this->isConnected()){
					return;
				}
				$currentWorld = $this->getPlayer()->getLocation()->getWorld();
				if($world !== $currentWorld or ($status = $this->getPlayer()->getUsedChunkStatus($chunkX, $chunkZ)) === null){
					$this->getLogger()->debug("Tried to send no-longer-active chunk $chunkX $chunkZ in world " . $world->getFolderName());
					return;
				}
				if(!$status->equals(UsedChunkStatus::REQUESTED_SENDING())){
					//TODO: make this an error
					//this could be triggered due to the shitty way that chunk resends are handled
					//right now - not because of the spammy re-requesting, but because the chunk status reverts
					//to NEEDED if they want to be resent.
					return;
				}
				$world->timings->syncChunkSend->startTiming();
				try{
					$this->queueCompressed($promise);
					$onCompletion();
				}finally{
					$world->timings->syncChunkSend->stopTiming();
				}
			}
		);
	}

	/**
	 * @throws ReflectionException
	 */
	public function tick() : void{
		if(!$this->isConnected()){
			ReflectionUtils::invoke(NetworkSession::class, $this, "dispose");
			return;
		}

		if(ReflectionUtils::getProperty(NetworkSession::class, $this, "info") === null){
			if(time() >= ReflectionUtils::getProperty(NetworkSession::class, $this, "connectTime") + 10){
				$this->disconnectWithError(KnownTranslationFactory::pocketmine_disconnect_error_loginTimeout());
			}

			return;
		}

		$player = ReflectionUtils::getProperty(NetworkSession::class, $this, "player");
		if($player !== null){
			$player->doChunkRequests();

			$dirtyAttributes = $player->getAttributeMap()->needSend();
			$this->getEntityEventBroadcaster()->syncAttributes([$this], $player, $dirtyAttributes);
			foreach($dirtyAttributes as $attribute){
				//TODO: we might need to send these to other players in the future
				//if that happens, this will need to become more complex than a flag on the attribute itself
				$attribute->markSynchronized();
			}
		}
		Timings::$playerNetworkSendInventorySync->startTiming();
		try{
			$this->getInvManager()?->flushPendingUpdates();
		}finally{
			Timings::$playerNetworkSendInventorySync->stopTiming();
		}

		$this->flushSendBuffer();
	}

	public function queueCompressed(CompressBatchPromise $payload, bool $immediate = false) : void{
		Timings::$playerNetworkSend->startTiming();
		try{
			$this->flushSendBuffer($immediate); //Maintain ordering if possible
			ReflectionUtils::invoke(NetworkSession::class, $this, "queueCompressedNoBufferFlush", $payload, $immediate);
		}finally{
			Timings::$playerNetworkSend->stopTiming();
		}
	}

	/**
	 * @throws ReflectionException
	 */
	public function setHandler(?PacketHandler $handler) : void{
		if(ReflectionUtils::getProperty(NetworkSession::class, $this, "connected")){ //TODO: this is fine since we can't handle anything from a disconnected session, but it might produce surprises in some cases
			if($handler instanceof InGamePacketHandler && ($handle = $this->getPacketTranslator()->handleInGame($this)) !== null){
				$handler = $handle;
			}
			ReflectionUtils::setProperty(NetworkSession::class, $this, "handler", $handler);
			if(($handler = ReflectionUtils::getProperty(NetworkSession::class, $this, "handler")) !== null){
				$handler->setUp();
			}
		}
	}
}