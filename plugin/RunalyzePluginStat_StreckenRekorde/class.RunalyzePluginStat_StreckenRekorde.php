<?php
/**
 * This file contains the class of the RunalyzePluginStat "StreckenRekorde".
 * @package Runalyze\Plugins\Stats
 */

use Runalyze\Model\Activity;
use Runalyze\Model\Route;
use Runalyze\View\Activity\Linker;
use Runalyze\Activity\Duration;
use Runalyze\Activity\Distance;
use Runalyze\Activity\Elevation;
use Runalyze\Model\Factory;
use Runalyze\Util\LocalTime;
use League\Geotools\Geotools;


$PLUGINKEY = 'RunalyzePluginStat_StreckenRekorde';

define("MAX_TIME", 999999);
define("MAX_DISTANCE", 999999);

/**
 * Class: RunalyzePluginStat_StreckenRekorde
 * @author Sven Henkel
 * @package Runalyze\Plugins\Stats
 */
class RunalyzePluginStat_StreckenRekorde extends PluginStat {
	private $SegementRecordData = array();
	private $counter = 0;
	private $activityRef = -1;


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
		return 12;
	}

	/**
	 * Init data 
     */
	protected function prepareForDisplay() {
		if (isset($_GET['ref']))                                                                                                                                     
        	if (is_numeric($_GET['ref']))                                                                                                                        
				$this->activityRef = $_GET['ref'];
		
		$this->setSportsNavigation(true, false);
		$this->setYearsNavigation(true, true, true);

		$this->setHeaderWithSportAndYear();

		$this->initSegmentRecordData();
		$this->initActivityLinks();
	}
	
	/**                                                                                                                                                                    
     * Returns the html-link for inner-html-navigation                                                                                                                     
     * @param string $name displayed link-name                                                                                                                             
     * @param int $sport id of sport, default $this->sportid                                                                                                               
     * @param int $year year, default $this->year                                                                                                                          
     * @param string $dat optional dat-parameter                                                                                                                           
     * @return string                                                                                                                                                      
     */                                                                                                                                                                    
    protected function getInnerLink($name, $sport = 0, $year = 0, $dat = '', $ref = 0) {
            if ($sport == 0) {                                                                                                                                             
                    $sport = $this->sportid;                                                                                                                               
            }                                                                                                                                                              
                                                                                                                                                                           
            if ($year == 0) {                                                                                                                                              
                    $year = $this->year;                                                                                                                                   
            }                                  
            
            if ($ref == 0) {
            		$ref = $this->activityRef;
            }
                                                                                                                                                                           
            return Ajax::link($name, 'statistics-inner', self::$DISPLAY_URL.'/'.$this->id().'?sport='.$sport.'&jahr='.$year.'&dat='.$dat.'&ref='.$ref);                                 
    }
	
	protected function getRefString() {
		if ($this->activityRef < 0) {
			return 'No Reference';
		}
		
		$Db = DB::getInstance();
		
		$activities = $Db->query('
			SELECT
				`'.PREFIX.'training`.`id`,
				`'.PREFIX.'training`.`time`,
				`'.PREFIX.'training`.`title`
			FROM `'.PREFIX.'training`
			WHERE `'.PREFIX.'training`.`accountid`="'.SessionAccountHandler::getId().'" 
			AND `'.PREFIX.'training`.`id`="'.$this->activityRef.'"
			AND `'.PREFIX.'training`.`routeid` IS NOT NULL '.$this->getSportAndYearDependenceForQuery().'
			LIMIT 1'
		)->fetchAll();
		
		foreach ($activities as $act) {
			$date = (new LocalTime($act["time"]))->format('d.m.Y');
			$txt = $date.' '.$act["title"];
			
			return $txt;
		}
		
		return 'n/a';
	}
	
	protected function getRefLinksAsList() {
		$list = '';
		
		if ($this->activityRef == -1) {
			$list .= '<li class="active">'.$this->getInnerLink("No reference", 0, 0, '', -1).'</li>';
		} else {
			$list .= '<li>'.$this->getInnerLink("No reference", 0, 0, '', -1).'</li>';
		}
		
		$Db = DB::getInstance();
		
		$activities = $Db->query('
			SELECT
				`'.PREFIX.'training`.`id`,
				`'.PREFIX.'training`.`time`,
				`'.PREFIX.'training`.`title`
			FROM `'.PREFIX.'training`
			WHERE `'.PREFIX.'training`.`accountid`="'.SessionAccountHandler::getId().'" 
			AND `'.PREFIX.'training`.`routeid` IS NOT NULL '.$this->getSportAndYearDependenceForQuery().'
			ORDER BY `'.PREFIX.'training`.`time` DESC
			LIMIT 20'
		)->fetchAll();
		
		foreach ($activities as $act) {
			$date = (new LocalTime($act["time"]))->format('d.m.Y');
			$txt = $date.' '.$act["title"];
			
			$list .= '<li>'.$this->getInnerLink($txt, 0, 0, '', $act["id"]).'</li>';
		}
		
		return $list;
	}
	
	
	protected function initActivityLinks() {
		$ll = array();
		$ll[] = '<li class="with-submenu"><span class="link">'.$this->getRefString().'</span><ul class="submenu">'.$this->getRefLinksAsList().'</ul>';
		$this->setToolbarNavigationLinks($ll);
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
		$this->displaySegmentRecordData();
		$this->outputJavascript();

		echo HTML::clearBreak();
	}
	
	private function displaySegmentRecordData() {
		echo '<div style="padding-left:30px; padding-right:30px">';
		echo '<div class="seg-tables">';
		foreach ($this->segmentRecordData as $segment) {
			
			$ref_time = -1;
			
			if ($this->activityRef != -1){
				// do we have an entry for our reference activity??
				$ref_found = 0;
				foreach ($segment["records"] as $record) {
					if ($record["activityid"] == $this->activityRef and $record["time"] < MAX_TIME) {
						$ref_found = 1;
						$ref_time = $record["time"];
						break;
					}
				}
				if ($ref_found == 0) {
					continue;
				}
			}
			
			echo '<div><table class="zebra-style center" style="margin-right: auto; margin-left: auto;">';
			echo '<thead><tr><th colspan="11" class="l">'.$segment["name"].'</th></tr></thead>';
			echo '<tbody>';
			$found = 0;
			foreach ($segment["records"] as $record) {
				if ($record["time"] < MAX_TIME) {
					$found = 1;
					if ($ref_time == -1) {
						$ref_time = $record["time"];
					}
					if ($record["activityid"] == $this->activityRef) {
						echo '<tr class="r highlight">';
					} else {
						echo '<tr class="r">';
					}
					$date = (new LocalTime($record["start_time"]))->format('d.m.Y');
					echo '<td class="b l">'.$date.'</td>';
					echo '<td class="b l">'.Ajax::trainingLink($record["activityid"], $this->labelFor($record["title"])).'</td>';
					echo '<td class="b l">'.Duration::format($record["time"]).'</td>';
					echo '<td class="b l">'.($ref_time==$record["time"]?' ':($ref_time>$record["time"]?'- '.Duration::format($ref_time-$record["time"]):'+ '.Duration::format($record["time"]-$ref_time))).'</td>';
					echo '<td class="b l">'.(array_key_exists("distance", $record)?Distance::format($record["distance"]):"n/a").'</td>';
					echo '</tr>';
				}
			}
			if ($found == 0) {
				echo '<tr><td colspan="4"><em>'.__('No routes found.').'</em></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div></div>';  //seg-tabless
		if ($this->counter == 0) {
			echo "Incomplete data!! Reload to continue processing.";
		}
	}
	
	private function outputJavascript() {
		echo '<script type="text/javascript" src="/assets/js/slick/slick.min.js"></script>';
		echo '<script type="text/javascript">';
		echo '  $(\'head\').append( $(\'<link rel="stylesheet" type="text/css" />\').attr(\'href\', \'/assets/js/slick/slick.css\') );';
		echo '  $(\'head\').append( $(\'<link rel="stylesheet" type="text/css" />\').attr(\'href\', \'/assets/js/slick/slick-theme.css\') );';
    	echo '  $(document).ready(function(){';
      	echo '    $(\'.seg-tables\').slick({';
      	echo '      arrows: true,';
      	echo '      infinite: false,';
      	echo '      dots: true';
      	echo '    });';
    	echo '  });';
  		echo '</script>';
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
						if (array_key_exists("distance", $c_rec)) {
                            $rec_entry["distance"] = $c_rec["distance"];
                        }
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
				$distance = MAX_DISTANCE;
				
				if (($s_id != -1) && ($e_id != -1)) {
					$trackdata = $Factory->trackdata($act["id"]);
					$time = $trackdata->time()[$e_id] - $trackdata->time()[$s_id];
					$distance = $trackdata->distance()[$e_id] - $trackdata->distance()[$s_id];
				} else {
					// wipe out comment and start time to save space on DB
					$rec_entry["title"] = NULL;
					$rec_entry["start_time"] = NULL;
				}
				
				$rec_entry["time"] = $time;
				$rec_entry["distance"] = $distance;
				array_push($records, $rec_entry);
				$c_rec = array("activityid" => $rec_entry["activityid"], "time" => $rec_entry["time"], "distance" => $rec_entry["distance"]);
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
