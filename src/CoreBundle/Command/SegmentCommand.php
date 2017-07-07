<?php

namespace Runalyze\Bundle\CoreBundle\Command;

use Runalyze\Bundle\CoreBundle\Entity\Account;
use Runalyze\Bundle\CoreBundle\Entity\Training;
use Runalyze\Bundle\CoreBundle\Entity\TrainingRepository;
use Runalyze\Util\LocalTime;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use ImporterFactory;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use League\Geotools\Coordinate\CoordinateInterface;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geohash\Geohash;

class SegmentCommand extends ContainerAwareCommand
{
    protected $failedImports = array();

    protected function configure()
    {
        $this
            ->setName('runalyze:segments')
            ->setDescription('View, create or edit segments')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addOption('create', null, InputOption::VALUE_NONE, 'Create a new segment')
            //->addOption('edit', null, InputOption::VALUE_NONE, 'Edit a segment')
            //->addOption('delete', null, InputOption::VALUE_NONE, 'Delete a segment')
            //->addOption('id', null, InputOption::VALUE_REQUIRED, 'Segment ID (edit or delete mode only)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title of segment')
            ->addOption('startlat', null, InputOption::VALUE_REQUIRED, 'Start latitude of segment')
            ->addOption('startlon', null, InputOption::VALUE_REQUIRED, 'Start longitude of segment')
            ->addOption('endlat', null, InputOption::VALUE_REQUIRED, 'End latitude of segment')
            ->addOption('endlon', null, InputOption::VALUE_REQUIRED, 'End longitute of segment')
            ;
    }

    /**
     * @param OutputInterface $output
     * @param string $message
     * @return int
     */
    protected function fail(OutputInterface $output, $message)
    {
        $output->writeln(sprintf('<error>%s</error>', $message));

        return 1;
    }
    
    protected function printSegments(OutputInterface $output, $accountid)
    {
        $prefix = $this->getContainer()->getParameter('database_prefix');

        $statement = $this->getContainer()->get('doctrine.dbal.default_connection')->prepare(
            'SELECT id, name, start, end FROM `'.$prefix.'segmentrecords` WHERE `accountid` = '.$accountid
        );

        $statement->execute();
        
        $segments = $statement->fetchAll();

        foreach ($segments AS $seg) {
            $startCoor = (new Geohash())->decode($seg["start"])->getCoordinate();
            $endCoor = (new Geohash())->decode($seg["end"])->getCoordinate();
            $start = $startCoor->getLatitude().' '.$startCoor->getLongitude();
            $end = $endCoor->getLatitude().' '.$endCoor->getLongitude();
            $output->writeln(sprintf('%s %s -> %s %s', $seg["id"], $start, $end, $seg["name"]));
        }
        
        //$output->writeln(sprintf('<error>%s</error>', $message));

        return 1;
    } 
    
    protected function createSegment(OutputInterface $output, $accountid, $t, $slat, $slon, $elat, $elon)
    {
        $prefix = $this->getContainer()->getParameter('database_prefix');
        
        $sCoor = new Coordinate($slat." ".$slon);
        $eCoor = new Coordinate($elat." ".$elon);
        
        $sGeo = (new Geohash())->encode($sCoor, 8)->getGeohash();
        $eGeo = (new Geohash())->encode($eCoor, 8)->getGeohash();
        
        $statement = $this->getContainer()->get('doctrine.dbal.default_connection')->prepare(
            'INSERT INTO `'.$prefix.'segmentrecords` (name, accountid, start, end) VALUES ("'.$t.'", '.$accountid.', "'.$sGeo.'", "'.$eGeo.'")'
        );
        
        //$output->writeln($sGeo);
        //$output->writeln($eGeo);

        $statement->execute();
        
        //$output->writeln(sprintf('<error>%s</error>', $message));

        return 1;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $doctrine = $this->getContainer()->get('doctrine');
        /** @var Account|null $account */
        $account = $doctrine->getRepository('CoreBundle:Account')->findByUsername($input->getArgument('username'));

        if (null === $account) {
            return $this->fail($output, 'Unknown account');
        }
        
        if ($input->getOption('create')) {
            $output->writeln("Creating new segment.");
            if (is_null($input->getOption('title'))) {
                return $this->fail($output, 'You have to specify a title.');
            }
            if (is_null($input->getOption('startlat'))) {
                return $this->fail($output, 'You have to specify a start latitude.');
            }
            if (is_null($input->getOption('startlon'))) {
                return $this->fail($output, 'You have to specify a start longitude.');
            }
            if (is_null($input->getOption('endlat'))) {
                return $this->fail($output, 'You have to specify an end latitude.');
            }
            if (is_null($input->getOption('endlon'))) {
                return $this->fail($output, 'You have to specify an end longitude.');
            }
            $this->createSegment($output, $account->getId(), $input->getOption('title'), $input->getOption('startlat'), $input->getOption('startlon'), $input->getOption('endlat'), $input->getOption('endlon'));
        }
        
        $this->printSegments($output, $account->getId());


        return null;
    }
}
