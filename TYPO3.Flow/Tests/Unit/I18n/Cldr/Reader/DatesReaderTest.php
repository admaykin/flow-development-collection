<?php
namespace TYPO3\Flow\Tests\Unit\I18n\Cldr\Reader;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Testcase for the DatesReader
 *
 */
class DatesReaderTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * Dummy locale used in methods where locale is needed.
     *
     * @var \TYPO3\Flow\I18n\Locale
     */
    protected $sampleLocale;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->sampleLocale = new \TYPO3\Flow\I18n\Locale('en');
    }

    /**
     * Setting cache expectations is partially same for many tests, so it's been
     * extracted to this method.
     *
     * @param \PHPUnit_Framework_MockObject_MockObject $mockCache
     * @return array
     */
    public function createCacheExpectations(\PHPUnit_Framework_MockObject_MockObject $mockCache)
    {
        $mockCache->expects($this->at(0))->method('has')->with('parsedFormats')->will($this->returnValue(true));
        $mockCache->expects($this->at(1))->method('has')->with('parsedFormatsIndices')->will($this->returnValue(true));
        $mockCache->expects($this->at(2))->method('has')->with('localizedLiterals')->will($this->returnValue(true));
        $mockCache->expects($this->at(3))->method('get')->with('parsedFormats')->will($this->returnValue(array()));
        $mockCache->expects($this->at(4))->method('get')->with('parsedFormatsIndices')->will($this->returnValue(array()));
        $mockCache->expects($this->at(5))->method('get')->with('localizedLiterals')->will($this->returnValue(array()));
        $mockCache->expects($this->at(6))->method('set')->with('parsedFormats');
        $mockCache->expects($this->at(7))->method('set')->with('parsedFormatsIndices');
        $mockCache->expects($this->at(8))->method('set')->with('localizedLiterals');
    }

    /**
     * @test
     */
    public function formatIsCorrectlyReadFromCldr()
    {
        $mockModel = $this->getAccessibleMock(\TYPO3\Flow\I18n\Cldr\CldrModel::class, array('getRawArray', 'getElement'), array(array()));
        $mockModel->expects($this->once())->method('getRawArray')->with('dates/calendars/calendar[@type="gregorian"]/dateFormats')->will($this->returnValue(array('default[@choice="medium"]' => '')));
        $mockModel->expects($this->once())->method('getElement')->with('dates/calendars/calendar[@type="gregorian"]/dateFormats/dateFormatLength[@type="medium"]/dateFormat/pattern')->will($this->returnValue('mockFormatString'));

        $mockRepository = $this->createMock(\TYPO3\Flow\I18n\Cldr\CldrRepository::class);
        $mockRepository->expects($this->once())->method('getModelForLocale')->with($this->sampleLocale)->will($this->returnValue($mockModel));

        $mockCache = $this->getMockBuilder(\TYPO3\Flow\Cache\Frontend\VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $this->createCacheExpectations($mockCache);

        $reader = $this->getAccessibleMock(\TYPO3\Flow\I18n\Cldr\Reader\DatesReader::class, array('parseFormat'));
        $reader->expects($this->once())->method('parseFormat')->with('mockFormatString')->will($this->returnValue('mockParsedFormat'));
        $reader->injectCldrRepository($mockRepository);
        $reader->injectCache($mockCache);
        $reader->initializeObject();

        $result = $reader->parseFormatFromCldr($this->sampleLocale, \TYPO3\Flow\I18n\Cldr\Reader\DatesReader::FORMAT_TYPE_DATE, \TYPO3\Flow\I18n\Cldr\Reader\DatesReader::FORMAT_LENGTH_DEFAULT);
        $this->assertEquals('mockParsedFormat', $result);

        $reader->shutdownObject();
    }

    /**
     * @test
     */
    public function dateTimeFormatIsParsedCorrectly()
    {
        $mockModel = $this->getAccessibleMock(\TYPO3\Flow\I18n\Cldr\CldrModel::class, array('getElement'), array(array()));
        $mockModel->expects($this->at(0))->method('getElement')->with('dates/calendars/calendar[@type="gregorian"]/dateTimeFormats/dateTimeFormatLength[@type="full"]/dateTimeFormat/pattern')->will($this->returnValue('foo {0} {1} bar'));
        $mockModel->expects($this->at(1))->method('getElement')->with('dates/calendars/calendar[@type="gregorian"]/dateFormats/dateFormatLength[@type="full"]/dateFormat/pattern')->will($this->returnValue('dMy'));
        $mockModel->expects($this->at(2))->method('getElement')->with('dates/calendars/calendar[@type="gregorian"]/timeFormats/timeFormatLength[@type="full"]/timeFormat/pattern')->will($this->returnValue('hms'));

        $mockRepository = $this->createMock(\TYPO3\Flow\I18n\Cldr\CldrRepository::class);
        $mockRepository->expects($this->exactly(3))->method('getModelForLocale')->with($this->sampleLocale)->will($this->returnValue($mockModel));

        $mockCache = $this->getMockBuilder(\TYPO3\Flow\Cache\Frontend\VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $this->createCacheExpectations($mockCache);

        $reader = new \TYPO3\Flow\I18n\Cldr\Reader\DatesReader();
        $reader->injectCldrRepository($mockRepository);
        $reader->injectCache($mockCache);
        $reader->initializeObject();

        $result = $reader->parseFormatFromCldr($this->sampleLocale, \TYPO3\Flow\I18n\Cldr\Reader\DatesReader::FORMAT_TYPE_DATETIME, \TYPO3\Flow\I18n\Cldr\Reader\DatesReader::FORMAT_LENGTH_FULL);
        $this->assertEquals(array(array('foo '), 'h', 'm', 's', array(' '), 'd', 'M', 'y', array(' bar')), $result);
        $reader->shutdownObject();
    }

    /**
     * @test
     */
    public function localizedLiteralsAreCorrectlyReadFromCldr()
    {
        $getRawArrayCallback = function () {
            $args = func_get_args();
            $mockDatesCldrData = require(__DIR__ . '/../../Fixtures/MockDatesParsedCldrData.php');

            $lastPartOfPath = substr($args[0], strrpos($args[0], '/') + 1);
            // Eras have different XML structure than other literals so they have to be handled differently
            if ($lastPartOfPath === 'eras') {
                return $mockDatesCldrData['eras'];
            } else {
                return $mockDatesCldrData[$lastPartOfPath];
            }
        };

        $mockModel = $this->getAccessibleMock(\TYPO3\Flow\I18n\Cldr\CldrModel::class, array('getRawArray'), array(array()));
        $mockModel->expects($this->exactly(5))->method('getRawArray')->will($this->returnCallback($getRawArrayCallback));

        $mockRepository = $this->createMock(\TYPO3\Flow\I18n\Cldr\CldrRepository::class);
        $mockRepository->expects($this->once())->method('getModelForLocale')->with($this->sampleLocale)->will($this->returnValue($mockModel));

        $mockCache = $this->getMockBuilder(\TYPO3\Flow\Cache\Frontend\VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $this->createCacheExpectations($mockCache);

        $reader = new \TYPO3\Flow\I18n\Cldr\Reader\DatesReader();
        $reader->injectCldrRepository($mockRepository);
        $reader->injectCache($mockCache);
        $reader->initializeObject();

        $result = $reader->getLocalizedLiteralsForLocale($this->sampleLocale);
        $this->assertEquals('January', $result['months']['format']['wide'][1]);
        $this->assertEquals('Sat', $result['days']['format']['abbreviated']['sat']);
        $this->assertEquals('1', $result['quarters']['format']['narrow'][1]);
        $this->assertEquals('a.m.', $result['dayPeriods']['stand-alone']['wide']['am']);
        $this->assertEquals('Anno Domini', $result['eras']['eraNames'][1]);

        $reader->shutdownObject();
    }

    /**
     * Data provider with valid format strings and expected results.
     *
     * @return array
     */
    public function formatStringsAndParsedFormats()
    {
        return array(
            array('yyyy.MM.dd G', array('yyyy', array('.'), 'MM', array('.'), 'dd', array(' '), 'G')),
            array('HH:mm:ss zzz', array('HH', array(':'), 'mm', array(':'), 'ss', array(' '), 'zzz')),
            array('EEE, MMM d, \'\'yy', array('EEE', array(','), array(' '), 'MMM', array(' '), 'd', array(','), array(' '), array('\''), 'yy')),
            array('hh \'o\'\'clock\' a, zzzz', array('hh', array(' '), array('o'), array('\''), array('clock'), array(' '), 'a', array(','), array(' '), 'zzzz')),
            array('QQyyLLLLDFEEEE', array('QQ', 'yy', 'LLLL', 'D', 'F', 'EEEE')),
            array('QQQMMMMMEEEEEwk', array('QQQ', 'MMMMM', 'EEEEE', 'w', 'k')),
            array('GGGGGKSWqqqqGGGGV', array('GGGGG', 'K', 'S', 'W', 'qqqq', 'GGGG', 'V')),
        );
    }

    /**
     * @test
     * @dataProvider formatStringsAndParsedFormats
     */
    public function formatStringsAreParsedCorrectly($format, $expectedResult)
    {
        $reader = $this->getAccessibleMock(\TYPO3\Flow\I18n\Cldr\Reader\DatesReader::class, array('dummy'));

        $result = $reader->_call('parseFormat', $format);
        $this->assertEquals($expectedResult, $result);
    }
}
