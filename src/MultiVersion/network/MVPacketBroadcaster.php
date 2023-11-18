<?php

namespace MultiVersion\network;

use InvalidArgumentException;
use MultiVersion\network\proto\PacketTranslator;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\BinaryStream;

class MVPacketBroadcaster implements PacketBroadcaster{

	public function __construct(
		private PacketTranslator $packetTranslator,
		private PacketSerializerContext $packetSerializerContext
	){

	}

	/**
	 * @param MVNetworkSession[]  $recipients
	 * @param ClientboundPacket[] $packets
	 */
	public function broadcastPackets(array $recipients, array $packets) : void{
		//TODO: this shouldn't really be called here, since the broadcaster might be replaced by an alternative
		//implementation that doesn't fire events
		$ev = new DataPacketSendEvent($recipients, $packets);
		$ev->call();
		if($ev->isCancelled()){
			return;
		}
		$packets = $ev->getPackets();

		$compressors = [];

		/** @var NetworkSession[][] $targetsByCompressor */
		$targetsByCompressor = [];
		foreach($recipients as $recipient){
			if($recipient->getPacketSerializerContext() !== $this->packetSerializerContext){
				throw new InvalidArgumentException("Only recipients with the same protocol context as the broadcaster can be broadcast to by this broadcaster");
			}

			//TODO: different compressors might be compatible, it might not be necessary to split them up by object
			$compressor = $recipient->getCompressor();
			$compressors[spl_object_id($compressor)] = $compressor;

			$targetsByCompressor[spl_object_id($compressor)][] = $recipient;
		}

		$totalLength = 0;
		$packetBuffers = [];
		foreach($packets as $packet){
			$pk = $this->packetTranslator->handleOutgoing(clone $packet);
			if($pk === null){
				continue;
			}
			$buffer = NetworkSession::encodePacketTimed($this->packetTranslator->getPacketSerializerFactory()->newEncoder($this->packetTranslator->getPacketSerializerFactory()->newSerializerContext()), $pk);
			//varint length prefix + packet buffer
			$totalLength += (((int) log(strlen($buffer), 128)) + 1) + strlen($buffer);
			$packetBuffers[] = $buffer;
		}

		foreach($targetsByCompressor as $compressorId => $compressorTargets){
			$compressor = $compressors[$compressorId];

			$threshold = $compressor->getCompressionThreshold();
			if(count($compressorTargets) > 1 && $threshold !== null && $totalLength >= $threshold){
				//do not prepare shared batch unless we're sure it will be compressed
				$stream = new BinaryStream();
				PacketBatch::encodeRaw($stream, $packetBuffers);
				$batchBuffer = $stream->getBuffer();

				$promise = Server::getInstance()->prepareBatch($batchBuffer, $compressor, timings: Timings::$playerNetworkSendCompressBroadcast);
				foreach($compressorTargets as $target){
					$target->queueCompressed($promise);
				}
			}else{
				foreach($compressorTargets as $target){
					foreach($packetBuffers as $packetBuffer){
						$target->addToSendBuffer($packetBuffer);
					}
				}
			}
		}
	}
}