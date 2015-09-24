<?php

/**
 * Delete a room
 * @param int $room_id
 */
function Room_delete($room_id) {
  return sql_query("DELETE FROM `Room` WHERE `RID`=" . sql_escape($room_id));
}

/**
 * Create a new room
 *
 * @param string $name
 *          Name of the room
 * @param boolean $from_frab
 *          Is this a frab imported room?
 * @param boolean $public
 *          Is the room visible for angels?
 * @param int $number room number
 * @param string $address room address
 *
 * @return int
 */
function Room_create($name, $from_frab, $public, $number = 0, $address = null, $color = null) {
  $result = sql_query("
      INSERT INTO `Room` SET 
      `Name`='" . sql_escape($name) . "', 
      `FromPentabarf`='" . sql_escape($from_frab ? 'Y' : 'N') . "', 
      `show`='" . sql_escape($public ? 'Y' : 'N') . "',
      `address`='" . sql_escape($address) . "',
      `color`='" . sql_escape($color) . "',
      `Number`='" . sql_escape(intval($number)) . "'");
  if ($result === false)
    return false;
  return sql_id();
}

/**
 * Returns room by id.
 *
 * @param $id RID          
 */
function Room($id) {
  $room_source = sql_select("SELECT * FROM `Room` WHERE `RID`='" . sql_escape($id) . "' AND `show` = 'Y'");
  
  if ($room_source === false)
    return false;
  if (count($room_source) > 0)
    return $room_source[0];
  return null;
}

?>
