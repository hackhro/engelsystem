<?php

function admin_shifts_title() {
  return _("Create shifts");
}

// Assistent zum Anlegen mehrerer neuer Schichten
function admin_shifts() {
  $ok = true;
  
  $rid = 0;
  $start = DateTime::createFromFormat("Y-m-d H:i", date("Y-m-d") . " 00:00")->getTimestamp();
  $end = $start;
  $mode = 'single';
  $angelmode = 'manually';
  $length = '';
  $change_hours = array();
  $static_hours = array();
  $title = "";
  $shifttype_id = null;
  
  // Locations laden (auch unsichtbare - fuer Erzengel ist das ok)
  $rooms = sql_select("SELECT * FROM `Room` ORDER BY `Name`");
  $room_array = array();
  foreach ($rooms as $room)
    $room_array[$room['RID']] = $room['Name'];
    
    // Engeltypen laden
  $types = sql_select("SELECT * FROM `AngelTypes` ORDER BY `name`");
  $needed_angel_types = array();
  foreach ($types as $type)
    $needed_angel_types[$type['id']] = 0;
    
    // Load shift types
  $shifttypes_source = ShiftTypes();
  if ($shifttypes_source === false)
    engelsystem_error('Unable to load shift types.');
  $shifttypes = [];
  foreach ($shifttypes_source as $shifttype)
    $shifttypes[$shifttype['id']] = $shifttype['name'];
  
  if (isset($_REQUEST['preview']) || isset($_REQUEST['back'])) {
    if (isset($_REQUEST['shifttype_id'])) {
      $shifttype = ShiftType($_REQUEST['shifttype_id']);
      if ($shifttype === false)
        engelsystem_error('Unable to load shift type.');
      if ($shifttype == null) {
        $ok = false;
        error(_('Please select a shift type.'));
      } else
        $shifttype_id = $_REQUEST['shifttype_id'];
    } else {
      $ok = false;
      error(_('Please select a shift type.'));
    }
    
    // Name/Bezeichnung der Schicht, darf leer sein
    $title = strip_request_item('title');
    
    // Auswahl der sichtbaren Locations für die Schichten
    if (isset($_REQUEST['rid']) && preg_match("/^[0-9]+$/", $_REQUEST['rid']) && isset($room_array[$_REQUEST['rid']]))
      $rid = $_REQUEST['rid'];
    else {
      $ok = false;
      $rid = $rooms[0]['RID'];
      error(_('Please select a location.'));
    }
    
    if (isset($_REQUEST['start']) && $tmp = DateTime::createFromFormat("Y-m-d H:i", trim($_REQUEST['start'])))
      $start = $tmp->getTimestamp();
    else {
      $ok = false;
      error(_('Please select a start time.'));
    }
    
    if (isset($_REQUEST['end']) && $tmp = DateTime::createFromFormat("Y-m-d H:i", trim($_REQUEST['end'])))
      $end = $tmp->getTimestamp();
    else {
      $ok = false;
      error(_('Please select an end time.'));
    }
    
    if ($start >= $end) {
      $ok = false;
      error(_('The shifts end has to be after its start.'));
    }
    
    if (isset($_REQUEST['mode'])) {
      if ($_REQUEST['mode'] == 'single') {
        $mode = 'single';
      } elseif ($_REQUEST['mode'] == 'static') {
        if (isset($_REQUEST['static_hours'])) {
          $mode = 'static';
          $matcher = "/^(\\d{1,2})(?:(?::)(\\d{2}))?$/";
          $formatNotice = "Use a format like 7, 07, 7:13, 07:13.";
          $hours = array_map("trim", explode(",", trim($_REQUEST['static_hours'])));
          foreach($hours as $hour) {
            $hour_parts = array_map("trim", explode("-", $hour));

            if(count($hour_parts) !== 2) {
              $ok = false;
              error(_('Please enter a static time with a hyphen like 07:00-18:00'));
              break;
            }

            list($time_start, $time_stop) = $hour_parts;

            if(!preg_match($matcher, $time_start, $matches)) {
              $ok = false;
              error(_('A start time is invalid. ' . $formatNotice));
              break;
            } else {
              $time_start_hour = intval($matches[1]);
              $time_start_minute = intval(isset($matches[2]) ? $matches[2] : 0);
            }

            if(!preg_match($matcher, $time_stop, $matches)) {
              $ok = false;
              error(_('An end time is invalid. ' . $formatNotice));
              break;
            } else {
              $time_stop_hour = intval($matches[1]);
              $time_stop_minute = intval(isset($matches[2]) ? $matches[2] : 0);
            }

            if(
                ($time_start_hour < 0 || $time_start_hour > 23) ||
                ($time_stop_hour < 0 || $time_stop_hour > 23) ||
                ($time_start_minute < 0 || $time_start_minute > 59) ||
                ($time_stop_minute < 0 || $time_stop_minute > 59)
            ) {
              $ok = false;
              error(_('An hour is less than 0 or greater than 23 or a minute is less than 0 or greater than 59'));
              break;
            }

            $static_hours[] = array(
                "start" => array("hour" => $time_start_hour, "minute" => $time_start_minute),
                "stop" => array("hour" => $time_stop_hour, "minute" => $time_stop_minute)
            );
          }
        }
      } elseif ($_REQUEST['mode'] == 'multi') {
        if (isset($_REQUEST['length']) && preg_match("/^[0-9]+$/", trim($_REQUEST['length']))) {
          $mode = 'multi';
          $length = trim($_REQUEST['length']);
        } else {
          $ok = false;
          error(_('Please enter a shift duration in minutes.'));
        }
      } elseif ($_REQUEST['mode'] == 'variable') {
        if (isset($_REQUEST['change_hours']) && preg_match("/^([0-9]{2}(,|$))/", trim(str_replace(" ", "", $_REQUEST['change_hours'])))) {
          $mode = 'variable';
          $change_hours = array_map('trim', explode(",", $_REQUEST['change_hours']));
        } else {
          $ok = false;
          error(_('Please split the shift-change hours by colons.'));
        }
      }
    } else {
      $ok = false;
      error(_('Please select a mode.'));
    }
    
    if (isset($_REQUEST['angelmode'])) {
      if ($_REQUEST['angelmode'] == 'location') {
        $angelmode = 'location';
      } elseif ($_REQUEST['angelmode'] == 'manually') {
        $angelmode = 'manually';
        foreach ($types as $type) {
          if (isset($_REQUEST['type_' . $type['id']]) && preg_match("/^[0-9]+$/", trim($_REQUEST['type_' . $type['id']]))) {
            $needed_angel_types[$type['id']] = trim($_REQUEST['type_' . $type['id']]);
          } else {
            $ok = false;
            error(sprintf(_('Please check the needed angels for team %s.'), $type['name']));
          }
        }
        if (array_sum($needed_angel_types) == 0) {
          $ok = false;
          error(_('There are 0 angels needed. Please enter the amounts of needed angels.'));
        }
      } else {
        $ok = false;
        error(_('Please select a mode for needed angels.'));
      }
    } else {
      $ok = false;
      error(_('Please select needed angels.'));
    }
    
    // Beim Zurück-Knopf das Formular zeigen
    if (isset($_REQUEST['back']))
      $ok = false;
      
      // Alle Eingaben in Ordnung
    if ($ok) {
      if ($angelmode == 'location') {
        $needed_angel_types = array();
        $needed_angel_types_location = sql_select("SELECT * FROM `NeededAngelTypes` WHERE `room_id`='" . sql_escape($rid) . "'");
        foreach ($needed_angel_types_location as $type)
          $needed_angel_types[$type['angel_type_id']] = $type['count'];
      }
      $shifts = array();
      if ($mode == 'single') {
        $shifts[] = array(
            'start' => $start,
            'end' => $end,
            'RID' => $rid,
            'title' => $title,
            'shifttype_id' => $shifttype_id
        );
      } elseif ($mode == 'static') {
        $shift_start = $start;

        do {
          foreach($static_hours as $_shift) {
            $_shift_start =
                $shift_start +
                ($_shift["start"]["hour"] * 60 * 60) +
                ($_shift["start"]["minute"] * 60);

            $_shift_end =
                $shift_start +
                ($_shift["stop"]["hour"] * 60 * 60) +
                ($_shift["stop"]["minute"] * 60);

            if ($_shift_start >= $_shift_end) {
              $_shift_end += 24 * 60 * 60;
            }

            $shifts[] = array(
                'start' => $_shift_start,
                'end' => $_shift_end,
                'RID' => $rid,
                'title' => $title,
                'shifttype_id' => $shifttype_id
            );
          }

          $shift_start += 24 * 60 * 60;
        } while($shift_start < $end);
      } elseif ($mode == 'multi') {
        $shift_start = $start;
        do {
          $shift_end = $shift_start + $length * 60;
          
          if ($shift_end > $end)
            $shift_end = $end;
          if ($shift_start >= $shift_end)
            break;
          
          $shifts[] = array(
              'start' => $shift_start,
              'end' => $shift_end,
              'RID' => $rid,
              'title' => $title,
              'shifttype_id' => $shifttype_id 
          );
          
          $shift_start = $shift_end;
        } while ($shift_end < $end);
      } elseif ($mode == 'variable') {
        rsort($change_hours);
        $day = DateTime::createFromFormat("Y-m-d H:i", date("Y-m-d", $start) . " 00:00")->getTimestamp();
        $change_index = 0;
        // Ersten/nächsten passenden Schichtwechsel suchen
        foreach ($change_hours as $i => $change_hour) {
          if ($start < $day + $change_hour * 60 * 60)
            $change_index = $i;
          elseif ($start == $day + $change_hour * 60 * 60) {
            // Start trifft Schichtwechsel
            $change_index = ($i + count($change_hours) - 1) % count($change_hours);
            break;
          } else
            break;
        }
        
        $shift_start = $start;
        do {
          $day = DateTime::createFromFormat("Y-m-d H:i", date("Y-m-d", $shift_start) . " 00:00")->getTimestamp();
          $shift_end = $day + $change_hours[$change_index] * 60 * 60;
          
          if ($shift_end > $end)
            $shift_end = $end;
          if ($shift_start >= $shift_end)
            $shift_end += 24 * 60 * 60;
          
          $shifts[] = array(
              'start' => $shift_start,
              'end' => $shift_end,
              'RID' => $rid,
              'title' => $title,
              'shifttype_id' => $shifttype_id 
          );
          
          $shift_start = $shift_end;
          $change_index = ($change_index + count($change_hours) - 1) % count($change_hours);
        } while ($shift_end < $end);
      }
      
      $shifts_table = array();
      foreach ($shifts as $shift) {
        $shifts_table_entry = [
            'timeslot' => '<span class="glyphicon glyphicon-time"></span> ' . date("Y-m-d H:i", $shift['start']) . ' - ' . date("H:i", $shift['end']) . '<br />' . Room_name_render(Room($shift['RID'])),
            'title' => ShiftType_name_render(ShiftType($shifttype_id)) . ($shift['title'] ? '<br />' . $shift['title'] : ''),
            'needed_angels' => '' 
        ];
        foreach ($types as $type)
          if (isset($needed_angel_types[$type['id']]) && $needed_angel_types[$type['id']] > 0)
            $shifts_table_entry['needed_angels'] .= '<b>' . AngelType_name_render($type) . ':</b> ' . $needed_angel_types[$type['id']] . '<br />';
        
        $shifts_table[] = $shifts_table_entry;
      }
      
      // Fürs Anlegen zwischenspeichern:
      $_SESSION['admin_shifts_shifts'] = $shifts;
      $_SESSION['admin_shifts_types'] = $needed_angel_types;
      
      $hidden_types = "";
      foreach ($needed_angel_types as $type_id => $count)
        $hidden_types .= form_hidden('type_' . $type_id, $count);
      return page_with_title(_("Preview"), array(
          form(array(
              $hidden_types,
              form_hidden('shifttype_id', $shifttype_id),
              form_hidden('title', $title),
              form_hidden('rid', $rid),
              form_hidden('start', date("Y-m-d H:i", $start)),
              form_hidden('end', date("Y-m-d H:i", $end)),
              form_hidden('mode', $mode),
              form_hidden('length', $length),
              form_hidden('change_hours', implode(', ', $change_hours)),
              form_hidden('angelmode', $angelmode),
              form_submit('back', _("back")),
              table(array(
                  'timeslot' => _('Time and location'),
                  'title' => _('Type and title'),
                  'needed_angels' => _('Needed angels') 
              ), $shifts_table),
              form_submit('submit', _("Save")) 
          )) 
      ));
    }
  } elseif (isset($_REQUEST['submit'])) {
    if (! is_array($_SESSION['admin_shifts_shifts']) || ! is_array($_SESSION['admin_shifts_types']))
      redirect(page_link_to('admin_shifts'));
    
    foreach ($_SESSION['admin_shifts_shifts'] as $shift) {
      $shift['URL'] = null;
      $shift['PSID'] = null;
      $shift_id = Shift_create($shift);
      if ($shift_id === false)
        engelsystem_error('Unable to create shift.');
      
      engelsystem_log("Shift created: " . $shifttypes[$shift['shifttype_id']] . " with title " . $shift['title'] . " from " . date("Y-m-d H:i", $shift['start']) . " to " . date("Y-m-d H:i", $shift['end']));
      $needed_angel_types_info = array();
      foreach ($_SESSION['admin_shifts_types'] as $type_id => $count) {
        $angel_type_source = sql_select("SELECT * FROM `AngelTypes` WHERE `id`='" . sql_escape($type_id) . "' LIMIT 1");
        if (count($angel_type_source) > 0) {
          sql_query("INSERT INTO `NeededAngelTypes` SET `shift_id`='" . sql_escape($shift_id) . "', `angel_type_id`='" . sql_escape($type_id) . "', `count`='" . sql_escape($count) . "'");
          $needed_angel_types_info[] = $angel_type_source[0]['name'] . ": " . $count;
        }
      }
    }
    
    engelsystem_log("Shift needs following angel types: " . join(", ", $needed_angel_types_info));
    success("Schichten angelegt.");
    redirect(page_link_to('admin_shifts'));
  } else {
    unset($_SESSION['admin_shifts_shifts']);
    unset($_SESSION['admin_shifts_types']);
  }
  
  if (! isset($_REQUEST['rid']))
    $_REQUEST['rid'] = null;
  $room_select = html_select_key('rid', 'rid', $room_array, $_REQUEST['rid']);
  $angel_types = "";
  foreach ($types as $type)
    $angel_types .= '<div class="col-md-4">' . form_spinner('type_' . $type['id'], $type['name'], $needed_angel_types[$type['id']]) . '</div>';
  
  return page_with_title(admin_shifts_title(), array(
      msg(),
      form(array(
          form_select('shifttype_id', _('Shifttype'), $shifttypes, $shifttype_id),
          form_text('title', _("Title"), $title),
          form_select('rid', _("Room"), $room_array, $_REQUEST['rid']),
          '<div class="row">',
          '<div class="col-md-6">',
          form_text('start', _("Start"), date("Y-m-d H:i", $start)),
          form_text('end', _("End"), date("Y-m-d H:i", $end)),
          form_info(_("Mode"), ''),
          form_radio('mode', _("Create one shift"), $mode == 'single', 'single'),
          form_radio('mode', _("Create multiple shifts"), $mode == 'multi', 'multi'),
          form_text('length', _("Length"), ! empty($_REQUEST['length']) ? $_REQUEST['length'] : '120'),
          form_radio('mode', _("Create multiple shifts with variable length"), $mode == 'variable', 'variable'),
          form_text('change_hours', _("Shift change hours"), ! empty($_REQUEST['change_hours']) ? $_REQUEST['change_hours'] : '00, 04, 08, 10, 12, 14, 16, 18, 20, 22'),
          form_radio('mode', _("Create shifts with specific periods"), $mode == 'static', 'static'),
          form_text('static_hours', _("Shift periods"), ! empty($_REQUEST['static_hours']) ? $_REQUEST['static_hours'] : '', false, "17:00-22:00, 05:00-07:00"),
          '</div>',
          '<div class="col-md-6">',
          form_info(_("Needed angels"), ''),
          form_radio('angelmode', _("Take needed angels from room settings"), $angelmode == 'location', 'location'),
          form_radio('angelmode', _("The following angels are needed"), $angelmode == 'manually', 'manually'),
          '<div class="row">'.$angel_types.'</div>',
          '</div>',
          '</div>',
          form_submit('preview', _("Preview")) 
      )) 
  ));
}
?>
