<?php
/**
 *  ______  __         ______               __    __
 * |   __ \|__|.-----.|   __ \.----..-----.|  |_ |  |--..-----..----.
 * |   __ <|  ||  _  ||   __ <|   _||  _  ||   _||     ||  -__||   _|
 * |______/|__||___  ||______/|__|  |_____||____||__|__||_____||__|
 *             |_____|
 *                                                                              
 * BigBrother plugin for PocketMine-MP
 * Copyright (C) 2014-2015 shoghicp <https://github.com/shoghicp/BigBrother>
 * Copyright (C) 2016- BigBrotherTeam
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author BigBrotherTeam
 * @link   https://github.com/BigBrotherTeam/BigBrother
 *
 */

namespace shoghicp\BigBrother\network;

use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use shoghicp\BigBrother\utils\Binary;
use shoghicp\BigBrother\utils\ConvertUtils;
use shoghicp\BigBrother\utils\ComputerItem;

abstract class Packet extends \stdClass{

	protected $buffer;
	protected $offset = 0;

	protected function get($len){
		if($len < 0){
			$this->offset = strlen($this->buffer) - 1;

			return "";
		}elseif($len === true){
			return substr($this->buffer, $this->offset);
		}

		$buffer = "";
		for(; $len > 0; --$len, ++$this->offset){
			$buffer .= @$this->buffer{$this->offset};
		}

		return $buffer;
	}

	protected function getLong(){
		return Binary::readLong($this->get(8));
	}

	protected function getInt(){
		return Binary::readInt($this->get(4));
	}

	protected function getPosition(&$x, &$y, &$z){
		$long = $this->getLong();
		$x = $long >> 38;
		$y = ($long >> 26) & 0xFFF;
		$z = $long << 38 >> 38;
	}

	protected function getFloat(){
		return Binary::readFloat($this->get(4));
	}

	protected function getDouble(){
		return Binary::readDouble($this->get(8));
	}

	/**
	 * @return Item
	 */
	protected function getSlot(){
		$itemId = $this->getShort();
		if($itemId === 65535){ //Empty
			return Item::get(Item::AIR, 0, 0);
		}else{
			$count = $this->getByte();
			$damage = $this->getShort();
			$len = $this->getByte();
			$nbt = "";
			if($len > 0){
				$nbt = $this->get($len);
			}

			$item = new ComputerItem($itemId, $damage, $count/*, $nbt*/);//TODO: Convert NBT

			ConvertUtils::convertItemData(false, $item);

			return $item;
		}
	}

	protected function putSlot(Item $item){
		ConvertUtils::convertItemData(true, $item);

		if($item->getID() === 0){
			$this->putShort(-1);
		}else{
			$this->putShort($item->getID());
			$this->putByte($item->getCount());
			$this->putShort($item->getDamage());

			$nbt = new NBT(NBT::LITTLE_ENDIAN);
			$nbt->read($item->getCompoundTag());//TODO String or CompoundTag
			$nbt = $nbt->getData();

			if($nbt->getType() !== NBT::TAG_End){
				ConvertUtils::convertNBTData(true, $nbt);

				$this->putByte(strlen($nbt));
				$this->put($nbt);
			}else{
				$this->putByte(0);
			}
		}
	}

	protected function getShort(){
		return Binary::readShort($this->get(2));
	}

	protected function getTriad(){
		return Binary::readTriad($this->get(3));
	}

	protected function getLTriad(){
		return Binary::readTriad(strrev($this->get(3)));
	}

	protected function getByte(){
		return ord($this->buffer{$this->offset++});
	}

	protected function getString(){
		return $this->get($this->getVarInt());
	}

	protected function getVarInt(){
		return Binary::readComputerVarInt($this->buffer, $this->offset);
	}

	protected function feof(){
		return !isset($this->buffer{$this->offset});
	}

	protected function put($str){
		$this->buffer .= $str;
	}

	protected function putLong($v){
		$this->buffer .= Binary::writeLong($v);
	}

	protected function putInt($v){
		$this->buffer .= Binary::writeInt($v);
	}

	protected function putPosition($x, $y, $z){
		$long = (($x & 0x3FFFFFF) << 38) | (($y & 0xFFF) << 26) | ($z & 0x3FFFFFF);
		$this->putLong($long);
	}

	protected function putFloat($v){
		$this->buffer .= Binary::writeFloat($v);
	}

	protected function putDouble($v){
		$this->buffer .= Binary::writeDouble($v);
	}

	protected function putShort($v){
		$this->buffer .= Binary::writeShort($v);
	}

	protected function putTriad($v){
		$this->buffer .= Binary::writeTriad($v);
	}

	protected function putLTriad($v){
		$this->buffer .= strrev(Binary::writeTriad($v));
	}

	protected function putByte($v){
		$this->buffer .= chr($v);
	}

	protected function putString($v){
		$this->putVarInt(strlen($v));
		$this->put($v);
	}

	protected function putVarInt($v){
		$this->buffer .= Binary::writeComputerVarInt($v);
	}

	public abstract function pid();

	protected abstract function encode();

	protected abstract function decode();

	public function write(){
		$this->buffer = "";
		$this->offset = 0;
		$this->encode();
		return Binary::writeComputerVarInt($this->pid()) . $this->buffer;
	}

	public function read($buffer, $offset = 0){
		$this->buffer = $buffer;
		$this->offset = $offset;
		$this->decode();
	}

}
