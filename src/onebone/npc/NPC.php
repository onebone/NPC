<?php

/*
 * Copyright (C) 2015-2016 onebone <jyc00410@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace onebone\npc;

use pocketmine\entity\Human;
use pocketmine\entity\Entity;
use pocketmine\level\Location;
use pocketmine\network\Network;
use pocketmine\Server;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\PlayerListPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\utils\UUID;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\math\Vector2;

class NPC extends Location{
	private $eid;
	private $skin, $skinName, $name;
	private $item, $message;

	private $uuid;

	public function __construct(Location $loc, $name, $skin, $skinName, Item $item, $message = ""){
		parent::__construct($loc->x, $loc->y, $loc->z, $loc->yaw, $loc->pitch, $loc->level);

		$this->eid = Entity::$entityCount++;
		$this->skin = $skin;
		$this->skinName = $skinName;
		$this->name = $name;
		$this->item = $item;
		$this->message = $message;

		$this->uuid = UUID::fromRandom();
	}

	public function getName(){
		return $this->name;
	}

	public function setMessage($msg){
		$this->message = $msg;
	}

	public function getMessage(){
		return $this->message;
	}

	public function getSkin(){
		return $this->skin;
	}

	public function setHoldingItem(Item $item){
		$this->item = $item->getId();
		$this->meta = $item->getDamage();
	}

	public function getId(){
		return $this->eid;
	}

	public function onInteract(Player $player){
		if($this->message !== ""){
			$player->sendMessage($this->message);
		}
	}

	public function seePlayer(Player $target){
		$pk = new MovePlayerPacket();
		$pk->eid = $this->eid;
		if($this->yaw === -1 and $target !== null){
			$xdiff = $target->x - $this->x;
			$zdiff = $target->z - $this->z;
			$angle = atan2($zdiff, $xdiff);
			$pk->yaw = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->yaw = $this->yaw;
		}
		if($this->pitch === -1 and $target !== null){
			$ydiff = $target->y - $this->y;

			$vec = new Vector2($this->x, $this->z);
			$dist = $vec->distance($target->x, $target->z);
			$angle = atan2($dist, $ydiff);
			$pk->pitch = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->pitch = $this->pitch;
		}
		$pk->x = $this->x;
		$pk->y = $this->y + 1.62;
		$pk->z = $this->z;
		$pk->bodyYaw = $pk->yaw;
		$pk->onGruond = 0;

		$target->dataPacket($pk);
	}

	public function spawnTo(Player $target){
		$pk = new AddPlayerPacket();
		$pk->uuid = $this->uuid;
		$pk->username = $this->name;
		$pk->eid = $this->eid;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		if($this->yaw === -1 and $target !== null){
			$xdiff = $target->x - $this->x;
			$zdiff = $target->z - $this->z;
			$angle = atan2($zdiff, $xdiff);
			$pk->yaw = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->yaw = $this->yaw;
		}
		if($this->pitch === -1 and $target !== null){

		}else{
			$pk->pitch = $this->pitch;
		}
		$pk->item = $this->item;
		$pk->metadata =
		[
			Entity::DATA_SHOW_NAMETAG => [
						Entity::DATA_TYPE_BYTE,
						1
				],
		];
		$target->dataPacket($pk);

		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_ADD;

		$pk->entries = [
			[
				$this->uuid, $this->eid, TextFormat::GRAY."NPC: ".$this->name, $this->skinName, $this->skin
			]
		];

		$target->dataPacket($pk);
	}

	public function removeFrom(Player $player){
		$pk = new RemovePlayerPacket();
		$pk->clientId = $this->uuid;
		$pk->eid = $this->eid;

		$player->dataPacket($pk);

		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_REMOVE;
		$pk->entries = [
			[
				$this->uuid, $this->eid, TextFormat::GRAY."NPC: ".$this->name, $this->skinName, $this->skin
			]
		];
		$player->dataPacket($pk);
	}

	public function remove(){
		foreach($this->level->getPlayers() as $player){
			$this->removeFrom($player);
		}
	}

	public function getSaveData(){
		return [
			$this->x, $this->y, $this->z, $this->level->getFolderName(),
			$this->yaw, $this->pitch,
			$this->eid, $this->item->getId(), $this->item->getDamage(), $this->name, $this->skinName, $this->message
		];
	}

	public static function createNPC($data){
		return new NPC(new Location($data[0], $data[1], $data[2], $data[4], $data[5], Server::getInstance()->getLevelByName($data[3])), $data[9], $data[6], $data[10], Item::get($data[7], $data[8]), $data[11]);
	}
}
