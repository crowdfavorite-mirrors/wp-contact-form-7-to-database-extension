<?php
include_once(dirname(dirname(__FILE__)) . '/ExportToHtmlTemplate.php');

include_once('MockQueryResultIterator.php');
include_once('WP_Mock_Functions.php');
include_once('WPDB_Mock.php');

class HtmlTemplateMissingFieldTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        date_default_timezone_set('America/New_York');
        $str = file_get_contents('HtmlTemplateMissingFieldTest.json');
        $data = json_decode($str, true);
        $mock = new MockQueryResultIterator($data);
        CFDBQueryResultIteratorFactory::getInstance()->setQueryResultsIteratorMock($mock);

        global $wpdb;
        $wpdb = new WPDB_Mock;

        $fields = array();
        foreach (array_keys($data[0]) as $key) {
            $fields[] = (object)array('field_name' => $key);
        }
        $wpdb->getResultReturnVal = $fields;
    }

    public function test_missing_lname_field() {
        $options = array();
        $options['content'] = '${fname} ${lname} | ';

        $exp = new ExportToHtmlTemplate();
        ob_start();
        $exp->export('dates', $options);
        $text = ob_get_contents();

        $this->assertEquals("Mike Simpson | Oya  | ", $text);
    }

}
