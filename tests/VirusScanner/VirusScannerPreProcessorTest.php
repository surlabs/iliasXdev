<?php

require_once('./libs/composer/vendor/autoload.php');

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\FileUpload\DTO\Metadata;
use ILIAS\FileUpload\DTO\ProcessingStatus;
use PHPUnit\Framework\TestCase;

/**
 * Class VirusScannerPreProcessorTest
 *
 * @author                 Fabian Schmid <fs@studer-raimann.ch>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState    disabled
 * @backupGlobals          disabled
 * @backupStaticAttributes disabled
 */
class VirusScannerPreProcessorTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;


    public function testVirusDetected()
    {
        $stream = Streams::ofString('Awesome stuff');
        $mock = $this->getMockBuilder(\ilVirusScanner::class)
                     ->disableOriginalConstructor()
                     ->getMock();
        $mock->expects($this->once())->method('scanFile')->with($stream->getMetadata('uri'))->willReturn("Virus found!!!");

        $subject = new ilVirusScannerPreProcessor($mock);
        $result = $subject->process($stream, new Metadata("MyVirus.exe", $stream->getSize(), 'application/vnd.microsoft.portable-executable'));
        $this->assertSame(ProcessingStatus::REJECTED, $result->getCode());
        $this->assertSame('Virus detected.', $result->getMessage());
    }


    public function testNoVirusDetected()
    {
        $stream = Streams::ofString('Awesome stuff');

        $mock = $this->getMockBuilder(\ilVirusScanner::class)
                     ->disableOriginalConstructor()
                     ->getMock();
        $mock->expects($this->once())->method('scanFile')->with($stream->getMetadata('uri'))->willReturn("");

        $subject = new ilVirusScannerPreProcessor($mock);
        $result = $subject->process($stream, new Metadata("MyVirus.exe", $stream->getSize(), 'application/vnd.microsoft.portable-executable'));
        $this->assertSame(ProcessingStatus::OK, $result->getCode());
    }
}
