<?php
/**
 * This file contains the class of the RunalyzePluginStat "StreckenRekorde".
 * @package Runalyze\Plugins\Stats
 */

use Runalyze\Model\Activity;
use Runalyze\Model\Route;
use Runalyze\View\Activity\Linker;
use Runalyze\Activity\Duration;
use Runalyze\Activity\Elevation;
use Runalyze\Model\Factory;
use Runalyze\Util\LocalTime;
use League\Geotools\Geotools;


$PLUGINKEY = 'RunalyzePluginStat_StreckenRekorde';

define("MAX_TIME", 999999);
/**
 * Class: RunalyzePluginStat_StreckenRekorde
 * @author Sven Henkel
 * @package Runalyze\Plugins\Stats
 */
class RunalyzePluginStat_StreckenRekorde extends PluginStat {
	private $SegementRecordData = array();
	private $counter = 0;


	/**
	 * Name
	 * @return string
	 */
	final public function name() {
		return __('Segment Records');
	}

	/**
	 * Description
	 * @return string
	 */
	final public function description() {
		return __('Your fastest times to get from point A to point B.');
	}
	
	/**
	 * Default year
	 * @return int
	 */
	protected function defaultYear() {
		return -1;
	}

	/**
	 * Init data 
	 */
	protected function prepareForDisplay() {
		$this->setSportsNavigation(true, false);
		$this->setYearsNavigation(true, true, true);

		$this->setHeaderWithSportAndYear();

		$this->initSegmentRecordData();
	}


	/**
	 * Title for all years
	 * @return string
	 */
	protected function titleForAllYears() {
		return __('All years');
	}

	/**
	 * Display the content
	 * @see PluginStat::displayContent()
	 */
	protected function displayContent() {
		$this->displaysegmentRecordData();

		echo HTML::clearBreak();
	}
	
	private function displaysegmentRecordData() {
		foreach ($this->segmentRecordData as $segment) {
			echo '<table class="fullwidth zebra-style">';
			echo '<thead><tr><th colspan="11" class="l">'.$segment["name"].'</th></tr></thead>';
			echo '<tbody>';
			$found = 0;
			foreach ($segment["records"] as $record) {
				if ($record["time"] < MAX_TIME) {
					$found = 1;
					echo '<tr class="r">';
					$date = (new LocalTime($record["start_time"]))->format('d.m.Y');
					echo '<td class="b l">'.$date.'</td>';
					echo '<td class="b l">'.Ajax::trainingLink($record["activityid"], $this->labelFor($record["title"])).'</td>';
					echo '<td class="b l">'.Duration::format($record["time"]).'</td>';
					echo '</tr>';
				}
			}
			if ($found == 0) {
				echo '<tr><td colspan="4"><em>'.__('No routes found.').'</em></td></tr>';
			}
			echo '</tbody></table>';
		}
		if ($this->counter == 0) {
			echo "Incomplete data!! Reload to continue processing.";
		}
	}
	
	private function initSegmentRecordData() {
		$Factory = new Factory((int)SessionAccountHandler::getId());
		$Geotools = new Geotools();
		$this->counter = 100;
		$Db = DB::getInstance();
		
		$activities = $Db->query('
			SELECT
				`'.PREFIX.'training`.`id`,
				`'.PREFIX.'training`.`time`,
				`'.PREFIX.'training`.`sportid`,
				`'.PREFIX.'training`.`title`,
				`'.PREFIX.'training`.`routeid`
			FROM `'.PREFIX.'training`
			WHERE `'.PREFIX.'training`.`accountid`="'.SessionAccountHandler::getId().'" 
			AND `'.PREFIX.'training`.`routeid` IS NOT NULL '.$this->getSportAndYearDependenceForQuery()
		)->fetchAll();
		
		$this->segmentRecordData = $Db->query('
			SELECT
				`'.PREFIX.'segmentrecords`.`id`,
				`'.PREFIX.'segmentrecords`.`name`,
				`'.PREFIX.'segmentrecords`.`start`,
				`'.PREFIX.'segmentrecords`.`end`,
				`'.PREFIX.'segmentrecords`.`cache`
			FROM `'.PREFIX.'segmentrecords`
			WHERE `'.PREFIX.'segmentrecords`.`accountid`="'.SessionAccountHandler::getId().'" ')->fetchAll();
			
		foreach ($this->segmentRecordData as &$segment) {
			$records = array();
			$cache = array();
			// Load from cache here if available
			if (!is_null($segment["cache"])) {
				$cache = json_decode($segment["cache"], true);
				if (!is_array($cache)) {
					$cache = array();
				}
			}
			
			$cache_needs_update = 0;
			
			// load coordinates
			$start = $Geotools->geohash()->decode($segment["start"])->getCoordinate();
			$end = $Geotools->geohash()->decode($segment["end"])->getCoordinate();

			// Loop through activties now
			foreach ($activities as $act) {
				$rec_entry = array();
				$rec_entry["activityid"] = $act["id"];
				$rec_entry["title"] = $act["title"];
				$rec_entry["start_time"] = $act["time"];
				
				// check if we already have a record from cache
				$cached = 0;
				foreach ($cache as $c_rec) {
					if ($c_rec["activityid"] == $rec_entry["activityid"]) {
						$cached = 1;
						$rec_entry["time"] = $c_rec["time"];
						array_push($records, $rec_entry);
						break;
					}
				}
				if ($cached == 1) {
					continue;
				}
				
				// not cached. do the work...
				if ($this->counter == 0) {
					continue;
				}
				
				$this->counter--;
				
				$route = $Factory->route($act["routeid"]);
				
				$s_id = -1;
				$e_id = -1;
				$ffwd = 0;
				
				//plausibility check: dist(r_st->seg_st) + dist(seg_st->seg_end) + dist(seg_end->r_end) < r_dist
				$r_start = $Geotools->geohash()->decode($route->get(Route\Entity::STARTPOINT))->getCoordinate();
				$r_end = $Geotools->geohash()->decode($route->get(Route\Entity::ENDPOINT))->getCoordinate();
				$d1 = $route->gpsDistance($r_start->getLatitude(), $r_start->getLongitude(), $start->getLatitude(), $start->getLongitude());
				$d2 = $route->gpsDistance($start->getLatitude(), $start->getLongitude(), $end->getLatitude(), $end->getLongitude());
				$d3 = $route->gpsDistance($end->getLatitude(), $end->getLongitude(), $r_end->getLatitude(), $r_end->getLongitude());
				if ($d1 + $d2 + $d3 <= $route->distance()) {
								
                    foreach ($route->geohashes() as $id => $geohash) {
                        //if ($act["id"] == 229) { print("ffwd: ".$ffwd."<br>\n"); }
                        if ($ffwd <= 0) {
                            $coor = $Geotools->geohash()->decode($geohash)->getCoordinate();
                        
                            if ($e_id == -1) {
                                // check if we are in starting area
                                $s_dist = $route->gpsDistance($coor->getLatitude(), $coor->getLongitude(), $start->getLatitude(), $start->getLongitude());
                                
                                if ($s_dist < 0.1) {
                                    $s_id = $id;
                                    //if ($act["id"] == 229) { print("s_id: ".$s_id."<br>\n"); }
                                } elseif ($s_dist > 1.0) {
                                    $ffwd = 10;
                                }
                                
                                if ($s_id != -1) {
                                    // check if we are in destination area
                                    $ffwd = 0;
                                    $e_dist = $route->gpsDistance($coor->getLatitude(), $coor->getLongitude(), $end->getLatitude(), $end->getLongitude());
                                
                                    if ($e_dist < 0.1) {
                                        $e_id = $id;
                                        //if ($act["id"] == 229) { print("e_id: ".$e_id."<br>\n"); }
                                        break;
                                    } elseif (($e_dist > 1.0) && (($s_dist > 1.0))) {
                                        $ffwd = 10;
                                    }
                                }
                            }
                        } else {
                            $ffwd--;
                        }
                    }
                }
                
				$time = MAX_TIME;
				
				if (($s_id != -1) && ($e_id != -1)) {
					$trackdata = $Factory->trackdata($act["id"]);
					$time = $trackdata->time()[$e_id] - $trackdata->time()[$s_id];
				} else {
					// wipe out comment and start time to save space on DB
					$rec_entry["title"] = NULL;
					$rec_entry["start_time"] = NULL;
				}
				
				$rec_entry["time"] = $time;
				array_push($records, $rec_entry);
				$c_rec = array("activityid" => $rec_entry["activityid"], "time" => $rec_entry["time"]);
				array_push($cache, $c_rec);
				$cache_needs_update = 1;
			}
			//sort $records array by time ascending
			usort($records,  function($a, $b) {
    			return $a['time'] - $b['time'];
			});
			
			$segment["records"] = $records;
			
			if ($cache_needs_update == 1) {
				$json = json_encode($cache);
				$Db->update("segmentrecords", $segment["id"], "cache", $json);
			}
		}
		//print_r($this->segmentRecordData);
	}
	
	/**
	 * Get label
	 * @param string $title
	 * @return string
	 */
	private function labelFor($title) {
		if (!is_null($title) && ($title != '')) {
			return '<em>'.$title.'</em>';
		}

		return '<em>'.__('unlabeled').'</em>';
	}
}
