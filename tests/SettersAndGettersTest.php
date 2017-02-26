<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\Test\CsvImporters\CsvImporter;

class SettersAndGettersTest extends BaseTestCase
{
    /** @test */
    public function setters_getters()
    {
        $importer = (new CsvImporter())->setCsvDateFormat('y-m-d')
            ->setDelimiter('d')
            ->setEnclosure('e')
            ->setEscape("x")
            ->setCsvFile(__DIR__.'/files/guitars.csv')
            ->setInputEncoding('RANCH DUBOIS-8')
            ->setOutputEncoding('Bird up-23')
            ->setNewline('newline');
        
        $this->assertEquals('y-m-d', $importer->csvDateFormat);
        $this->assertEquals('y-m-d', $importer->getCsvDateFormat());
        $this->assertEquals('y-m-d', $importer->yo('CsvDateFormat'));
        $this->assertEquals('d', $importer->delimiter);
        $this->assertEquals('d', $importer->getDelimiter());
        $this->assertEquals('d', $importer->commentCaVa('Delimiter'));
        $this->assertEquals('e', $importer->enclosure);
        $this->assertEquals('e', $importer->getEnclosure());
        $this->assertEquals('e', $importer->caVa('Enclosure'));
        $this->assertEquals('x', $importer->escape);
        $this->assertEquals('x', $importer->getEscape());
        $this->assertEquals('x', $importer->quelleEstVotreBurritoAmigoQuestionMark('escape'));
        $this->assertEquals(__DIR__.'/files/guitars.csv', $importer->csvFile);
        $this->assertEquals(__DIR__.'/files/guitars.csv', $importer->getCsvFile());
        $this->assertEquals(__DIR__.'/files/guitars.csv', $importer->supAsapUltraDigitalCoruscant('csvFile'));
        $this->assertEquals('RANCH DUBOIS-8', $importer->inputEncoding);
        $this->assertEquals('RANCH DUBOIS-8', $importer->getInputEncoding());
        $this->assertEquals('RANCH DUBOIS-8', $importer->dghstr('inputEncoding'));
        $this->assertEquals('Bird up-23', $importer->outputEncoding);
        $this->assertEquals('Bird up-23', $importer->getOutputEncoding());
        $this->assertEquals('Bird up-23', $importer->jeMappelleRoman('outputEncoding'));
        $this->assertEquals('newline', $importer->newline);
        $this->assertEquals('newline', $importer->getNewline());
        $this->assertEquals('newline', $importer->GimmeFuelGimmeFireGimmeThatWhichIDesireExlamationMark('newline'));
    }
}