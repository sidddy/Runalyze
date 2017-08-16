<?php

namespace Runalyze\Bundle\CoreBundle\Command;

use Doctrine\ORM\Query;
use Runalyze\Bundle\CoreBundle\Entity\Account;
use Runalyze\Bundle\CoreBundle\Entity\AccountRepository;
use Runalyze\Bundle\CoreBundle\Entity\RaceresultRepository;
use Runalyze\Bundle\CoreBundle\Entity\TrainingRepository;
use Runalyze\Bundle\CoreBundle\Entity\SportRepository;
use Runalyze\Bundle\CoreBundle\Entity\TypeRepository;
use Runalyze\Bundle\CoreBundle\Entity\EquipmentRepository;
use Runalyze\Model;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Filesystem\Filesystem;
use Runalyze\Service\ElevationCorrection\StepwiseElevationProfileFixer;
use Runalyze\Bundle\CoreBundle\Bridge\Activity\Calculation\ClimbScoreCalculator;
use Runalyze\Bundle\CoreBundle\Bridge\Activity\Calculation\FlatOrHillyAnalyzer;
use Runalyze\Bundle\CoreBundle\Bridge\Activity\Calculation\ClimbFinder;
use Runalyze\Util\LocalTime; 

class AutotagCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('runalyze:autotag')
            ->setDescription('Autotag my activities.')
        ;
    }

    protected function tagTitle(InputInterface $input, OutputInterface $output)
    {
	$doctrine = $this->getContainer()->get('doctrine');

	/** @var TrainingRepository $trainingRepository */
	$trainingRepository = $doctrine->getRepository('CoreBundle:Training');
	$typeRepository = $doctrine->getRepository('CoreBundle:Type');
	$equipmentRepository = $doctrine->getRepository('CoreBundle:Equipment');

	$type = $typeRepository->find(25);
	$equipment = $equipmentRepository->find(26);
	
	$queryBuilder = $trainingRepository->createQueryBuilder('t')
		->where('t.account = :account')
		->andWhere('t.title = \'\'')
		->andWhere('t.time > 1483261090')
		->setParameters([ ':account' => 3 ]);

	$activities = $queryBuilder->getQuery()->getResult();

	foreach ($activities as $act) {
		$output->writeln("Processing Activity ID ".$act->getID());	
		// eBike to work?
		// is it biking?
		if ($act->getSport()->getId() == 13 AND is_null($act->getType())) {
			// check day of week
			if (is_numeric($act->getTime())) {
				$lt = new LocalTime($act->getTime());
       		 		$w = $lt->format('w');
				$h = $lt->format('G');
				$dir = 0;
				if ($w >= 1 AND $w <= 4) { // Mo-Thu
					if ($h >= 7 AND $h <= 8) {
						$dir = 1;
					} elseif ($h >=16 AND $h <=17) {
						$dir = 2;
					}
				}
				if ($dir != 0) {
					// check duration 21..30 min
					if ($act->getS() >= 21*60 AND $act->getS() <= 30*60) {
						$output->writeln("Fits so far. Direction: ".$dir." Duration: ".$act->getS()." Type: ".$act->getType());
						// fields to be set: 
						// - title
						// - equipment
						// - sport type
						// distance
						// elevation
						if ($dir == 1) {
							$act->setTitle("EBK zur Arbeit");
						} elseif ($dir == 2) {
							$act->setTitle("EBK nach Hause");
						}
						$act->addEquipment($equipment);
						$act->setType($type);
						$act->setDistance(9);
						$act->setElevation(20);
						$trainingRepository->save($act);
					}
				}
			}
		}
	}

	return 0;
    }

    /**
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
	$this->tagTitle($input, $output);
        return 0;
    }

}
