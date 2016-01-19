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
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\math\Vector2;

class NPC{
	private $eid;
	public $pos;
	private $skin, $skinName, $name;
	private $item, $message;

	private $uuid;

	public function __construct(Location $loc, $name, $skin, $skinName, Item $item, $message = ""){
		$this->pos = $loc;
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

	public function setX($x){
		$this->pos->x = $x;
	}

	public function setY($y){
		$this->pos->y = $y;
	}

	public function setZ($z){
		$this->pos->z = $z;
	}

	public function setYaw($yaw){
		$this->pos->yaw = $yaw;
	}

	public function setPitch($pitch){
		$this->pos->pitch = $pitch;
	}

	public function getLevel(){
		return $this->pos->level;
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
		if($this->pos->yaw === -1 and $target !== null){
			$xdiff = $target->x - $this->pos->x;
			$zdiff = $target->z - $this->pos->z;
			$angle = atan2($zdiff, $xdiff);
			$pk->yaw = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->yaw = $this->pos->yaw;
		}
		if($this->pos->pitch === -1 and $target !== null){
			$ydiff = $target->y - $this->pos->y;

			$vec = new Vector2($this->pos->x, $this->pos->z);
			$dist = $vec->distance($target->x, $target->z);
			$angle = atan2($dist, $ydiff);
			$pk->pitch = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->pitch = $this->pitch;
		}
		$pk->x = $this->pos->x;
		$pk->y = $this->pos->y + 1.62;
		$pk->z = $this->pos->z;
		$pk->bodyYaw = $pk->yaw;
		$pk->onGruond = 0;

		$target->dataPacket($pk);
	}

	public function spawnTo(Player $target){
		$pk = new AddPlayerPacket();
		$pk->uuid = $this->uuid;
		$pk->username = $this->name;
		$pk->eid = $this->eid;
		$pk->x = $this->pos->x;
		$pk->y = $this->pos->y;
		$pk->z = $this->pos->z;
		if($this->pos->yaw === -1 and $target !== null){
			$xdiff = $target->x - $this->pos->x;
			$zdiff = $target->z - $this->pos->z;
			$angle = atan2($zdiff, $xdiff);
			$pk->yaw = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->yaw = $this->pos->yaw;
		}
		if($this->pos->pitch === -1 and $target !== null){

		}else{
			$pk->pitch = $this->pos->pitch;
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
				$this->uuid, $this->eid, "NPC: ".$this->name, $this->skinName, $this->skin
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
		$pk->type = PlayerListPacket::TYPE_ADD;
		$pk->entries = [
			[
				$this->uuid, $this->eid, "NPC: ".$this->name, $this->skinName, $this->skin
			]
		];
		$target->dataPacket($pk);
	}

	public function remove(){
		$pk = new RemovePlayerPacket();
		$pk->eid = $this->eid;
		$pk->clientId = $this->uuid;
		$players = $this->pos->level->getPlayers();
		foreach($players as $player){
			$player->dataPacket($pk);
		}
	}

	public function getSaveData(){
		return [
			$this->pos->x, $this->pos->y, $this->pos->z, $this->pos->level->getFolderName(),
			$this->pos->yaw, $this->pos->pitch,
			$this->eid, $this->item->getId(), $this->item->getDamage(), $this->name, $this->skinName, $this->message
		];
	}

	public static function createNPC($data){
		return new NPC(new Location($data[0], $data[1], $data[2], $data[4], $data[5], Server::getInstance()->getLevelByName($data[3])), $data[9], $data[6], $data[10], Item::get($data[7], $data[8]), $data[11]);
	}
}
