<?php

namespace Runalyze\Calculation\Route;

use Runalyze\Calculation\Math\MovingAverage\Kernel\AbstractKernel;
use Runalyze\Calculation\Math\MovingAverage\WithKernel;
use Runalyze\Mathematics\Numerics\Derivative;
use Runalyze\Model;

class VAM500
{
	/** @var array */
	protected $Elevation = [];

    /** @var array */
    protected $Distance = [];

    /** @var array  */
    protected $VAM = [];

    /** @var Time */
    protected $Time = [];

    /** @var AbstractKernel|null */
    protected $MovingAverageKernel = null;

	/**
     * @param array $elevation
     * @param array $distance
	 */
	public function __construct(array $elevation = [], array $distance = [])
    {
        if (!empty($elevation) && !empty($distance)) {
            $this->setData($elevation, $distance);
        }
	}

    /**
     * @param array $elevation
     * @param array $distance
     * @throws \InvalidArgumentException
     */
    public function setData(array $elevation, array $distance)
    {
        if (count($elevation) !== count($distance)) {
            throw new \InvalidArgumentException('Input arrays must be of same size.');
        }

        $this->Elevation = $elevation;
        $this->Distance = $distance;
    }

    /**
     * @param Model\Route\Entity $route
     * @param Model\Trackdata\Entity $trackdata
     * @throws \InvalidArgumentException
     */
    public function setDataFrom(Model\Route\Entity $route, Model\Trackdata\Entity $trackdata)
    {
        if (!$route->hasElevations() || !$trackdata->has(Model\Trackdata\Entity::DISTANCE) || !$trackdata->has(Model\Trackdata\Entity::TIME)) {
            throw new \InvalidArgumentException('Route must have elevations and trackdata must have time & distances.');
        }

        $this->Elevation = $route->elevations();
        $this->Distance = $trackdata->distance();
	$this->Time = $trackdata->time();
    }

    /**
     * @param AbstractKernel $kernel the kernel will be applied to elevation data only without using distance as index
     */
    public function setMovingAverageKernel(AbstractKernel $kernel)
    {
        $this->MovingAverageKernel = $kernel;
    }

    public function calculate()
    {
        if (empty($this->Elevation)) {
            return;
        }

        $elevation = $this->Elevation;

        $this->applyMovingAverage($elevation);

        $d_pos = 0;
	for($i = 0; $i < count($this->Distance); $i++) {
		while ($this->Distance[$i] - $this->Distance[$d_pos] > 0.5) {
			$d_pos++;
		} 
		
		if ($d_pos < $i) {	
			$val = (($elevation[$i]-$elevation[$d_pos])/($this->Time[$i]-$this->Time[$d_pos]))*3600;
			if ($val < 0) {
				$val = 0;
			}
			array_push($this->VAM,$val);
		} else {
			array_push($this->VAM,0);
		}
        }
    }

    /**
     * @param array $elevation
     */
    protected function applyMovingAverage(array &$elevation)
    {
        if (null !== $this->MovingAverageKernel) {
            $movingAverage = new WithKernel($elevation);
            $movingAverage->setKernel($this->MovingAverageKernel);
            $movingAverage->calculate();

            $elevation = $movingAverage->movingAverage();
        }
    }

    /**
     * @return array
     */
    public function getSeries()
    {
        return $this->VAM;
    }
}
