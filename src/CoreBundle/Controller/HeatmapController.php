<?php

// src/CoreBundle/Controller/HeatmapController.php

namespace Runalyze\Bundle\CoreBundle\Controller;                                                                                                     
                                                                                                                                                     
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;                                                                                          
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;                                                                                       
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Runalyze\Configuration;
use Runalyze\View\Leaflet;
use Runalyze\Model;
use DB;
use Ajax;

use League\Geotools\Geohash\Geohash;

class HeatmapController {

  /**
   * @Route("/heatmap")
   */
  public function heatmapAction() { 
    $Frontend = new \Frontend(true, null);
    Configuration::loadAll(3);

    $content = "<html>\n".
               "<head>\n".
               "<meta charset=\"UTF-8\">\n".
               "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=yes\">\n".
               "<base href=\"https://run.siddy.org/\">\n".
               "<link rel=\"stylesheet\" href=\"/assets/css/runalyze-style.css?v=4.0.0\">\n".
               "<title>RUNALYZE</title>\n".
               "<script>document.addEventListener(\"touchstart\", function(){}, true);</script>\n".
               "<script src=\"/assets/js/scripts.min.js?v=4.0.0\"></script> </head>\n";
    echo $content;
    Ajax::initJSlibrary(); 
   
    $content = "<body>\n";

    $Routes = DB::getInstance()->query('SELECT `'.PREFIX.'route`.`id`, `'.PREFIX.'route`.`geohashes`, `'.PREFIX.'route`.`min`, `'.PREFIX.'route`.`max` FROM `'.PREFIX.'training` LEFT JOIN `'.PREFIX.'route` ON `'.PREFIX.'training`.`routeid`=`'.PREFIX.'route`.`id` WHERE `'.PREFIX.'training`.`accountid`=3 AND `geohashes`!="" AND `'.PREFIX.'training`.`sportid`=13 ORDER BY `id` DESC LIMIT 1000');

    $Map = new Leaflet\Map('map-heatmap');
    $minLat = 90;
    $maxLat = -90;
    $minLng = 180;
    $maxLng = -180;
//    $content .= "DEBUG START\n";
    while ($RouteData = $Routes->fetch()) {
      $Route = new Model\Route\Entity($RouteData);
      if (null !== $RouteData['min'] && null !== $RouteData['max']) {
        $MinCoordinate = (new Geohash())->decode($RouteData['min'])->getCoordinate();
        $MaxCoordinate = (new Geohash())->decode($RouteData['max'])->getCoordinate();
        $minLat = $MinCoordinate->getLatitude() != 0 ? min($minLat, $MinCoordinate->getLatitude()) : $minLat;
        $minLng = $MinCoordinate->getLongitude() != 0 ? min($minLng, $MinCoordinate->getLongitude()) : $minLng;
        $maxLat = $MaxCoordinate->getLatitude() != 0 ? max($maxLat, $MaxCoordinate->getLatitude()) : $maxLat;
        $maxLng = $MaxCoordinate->getLongitude() != 0 ? max($maxLng, $MaxCoordinate->getLongitude()) : $maxLng;
      }
      $Path = new Leaflet\Activity('route-'.$RouteData['id'], $Route, null, false);
      $Path->addOption('hoverable', false);
      $Path->addOption('autofit', false);
      $Map->addRoute($Path);
    }
//    $content .= "DEBUG END\n";
    $Map->setBounds(array( 'lat.min' => $minLat, 'lat.max' => $maxLat, 'lng.min' => $minLng, 'lng.max' => $maxLng ));

    $content .= $Map->code();
    $content .= "<script>RunalyzeLeaflet.toggleFullscreen();</script>";

    $content .= "</body></html>\n";
    $response =  new Response($content); 
    return $response;
 }
}
