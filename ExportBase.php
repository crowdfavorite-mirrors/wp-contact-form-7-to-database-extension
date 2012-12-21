<?php
/*
    "Contact Form to Database Extension" Copyright (C) 2011 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This file is part of Contact Form to Database Extension.

    Contact Form to Database Extension is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Contact Form to Database Extension is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database Extension.
    If not, see <http://www.gnu.org/licenses/>.
*/

require_once('CF7DBPlugin.php');
require_once('CFDBQueryResultIterator.php');

class ExportBase {

    /**
     * @var string
     */
    var $defaultTableClass = 'cf7-db-table';

    /**
     * @var array
     */
    var $options;

    /**
     * @var bool
     */
    var $debug = false;

    /**
     * @var array
     */
    var $showColumns;

    /**
     * @var array
     */
    var $hideColumns;

    /**
     * @var string
     */
    var $htmlTableId;

    /**
     * @var string
     */
    var $htmlTableClass;

    /**
     * @var string
     */
    var $style;

    /**
     * @var CF7DBEvalutator|CF7FilterParser|CF7SearchEvaluator
     */
    var $rowFilter;

    /**
     * @var bool
     */
    var $isFromShortCode = false;

    /**
     * @var bool
     */
    var $showSubmitField;

    /**
     * @var CF7DBPlugin
     */
    var $plugin;

    /**
     * @var CFDBQueryResultIterator
     */
    var $dataIterator;

    function __construct() {
        $this->plugin = new CF7DBPlugin();
    }

    /**
     * This method is the first thing to call after construction to set state for other methods to work
     * @param  $options array|null
     * @return void
     */
    protected function setOptions($options) {
        $this->options = $options;
    }

    protected function setCommonOptions($htmlOptions = false) {

        if ($this->options && is_array($this->options)) {
            if (isset($this->options['debug']) && $this->options['debug'] != 'false') {
                $this->debug = true;
            }

            $this->isFromShortCode = isset($this->options['fromshortcode']) &&
                    $this->options['fromshortcode'] === true;

            if (!isset($this->options['unbuffered'])) {
                $this->options['unbuffered'] = $this->isFromShortCode ? 'false' : 'true';
            }

            if (isset($this->options['showColumns'])) {
                $this->showColumns = $this->options['showColumns'];
            }
            else if (isset($this->options['show'])) {
                $this->showColumns = preg_split('/,/', $this->options['show'], -1, PREG_SPLIT_NO_EMPTY);
            }

            if (isset($this->options['hideColumns'])) {
                $this->hideColumns = $this->options['hideColumns'];
            }
            else if (isset($this->options['hide'])) {
                $this->hideColumns = preg_split('/,/', $this->options['hide'], -1, PREG_SPLIT_NO_EMPTY);
            }


            if ($htmlOptions) {
                if (isset($this->options['class'])) {
                    $this->htmlTableClass = $this->options['class'];
                }
                else {
                    $this->htmlTableClass = $this->defaultTableClass;
                }

                if (isset($this->options['id'])) {
                    $this->htmlTableId = $this->options['id'];
                }
                else {
                    $this->htmlTableId = 'cftble_' . rand();
                }

                if (isset($this->options['style'])) {
                    $this->style = $this->options['style'];
                }
            }


            if (isset($this->options['filter'])) {
                require_once('CF7FilterParser.php');
                require_once('DereferenceShortcodeVars.php');
                $this->rowFilter = new CF7FilterParser;
                $this->rowFilter->setComparisonValuePreprocessor(new DereferenceShortcodeVars);
                $this->rowFilter->parseFilterString($this->options['filter']);
                if ($this->debug) {
                    echo '<pre>\'' . $this->options['filter'] . "'\n";
                    print_r($this->rowFilter->tree);
                    echo '</pre>';
                }
            }
            else if (isset($this->options['search'])) {
                require_once('CF7SearchEvaluator.php');
                $this->rowFilter = new CF7SearchEvaluator;
                $this->rowFilter->setSearch($this->options['search']);
            }
        }
    }

    protected function isAuthorized() {
        return $this->isFromShortCode ?
                $this->plugin->canUserDoRoleOption('CanSeeSubmitDataViaShortcode') :
                $this->plugin->canUserDoRoleOption('CanSeeSubmitData');
    }

    protected function assertSecurityErrorMessage() {
        $errMsg = __('You do not have sufficient permissions to access this data.', 'contact-form-7-to-database-extension');
        if ($this->isFromShortCode) {
            echo $errMsg;
        }
        else {
            include_once('CFDBDie.php');
            CFDBDie::wp_die($errMsg);
        }
    }


    /**
     * @param string|array|null $headers mixed string header-string or array of header strings.
     * E.g. Content-Type, Content-Disposition, etc.
     * @return void
     */
    protected function echoHeaders($headers = null) {
        if (!headers_sent()) {
            header('Expires: 0');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            // Hoping to keep the browser from timing out if connection from Google SS Live Data
            // script is calling this page to get information
            header("Keep-Alive: timeout=60"); // Not a standard HTTP header; browsers may disregard

            if ($headers) {
                if (is_array($headers)) {
                    foreach ($headers as $aheader) {
                        header($aheader);
                    }
                }
                else {
                    header($headers);
                }
            }
            flush();
        }
    }

    /**
     * @param  $dataColumns array
     * @return array
     */
    protected function &getColumnsToDisplay($dataColumns) {

        if (empty($dataColumns)) {
            $retCols = array();
            return $retCols;
        }

        //$dataColumns = array_merge(array('Submitted'), $dataColumns);
        $showCols = empty($this->showColumns) ? $dataColumns : $this->matchColumns($this->showColumns, $dataColumns);
        if (empty($this->hideColumns)) {
            return $showCols;
        }

        $hideCols = $this->matchColumns($this->hideColumns, $dataColumns);
        if (empty($hideCols)) {
            return $showCols;
        }

        $retCols = array();
        foreach ($showCols as $aShowCol) {
            if (!in_array($aShowCol, $hideCols)) {
                $retCols[] = $aShowCol;
            }
        }
        return $retCols;
    }

    protected function matchColumns(&$patterns, &$subject) {
        $returnCols = array();
        foreach ($patterns as $pCol) {
            if (substr($pCol, 0, 1) == '/') {
                // Show column value is a REGEX
                foreach($subject as $sCol) {
                    if (preg_match($pCol, $sCol) && !in_array($sCol, $returnCols)) {
                        $returnCols[] = $sCol;
                    }
                }
            }
            else {
                $returnCols[] = $pCol;
            }
        }
        return $returnCols;
    }

    /**
     * @return bool
     */
    protected function getShowSubmitField() {
        $showSubmitField = true;
        if ($this->hideColumns != null && is_array($this->hideColumns) && in_array('Submitted', $this->hideColumns)) {
            $showSubmitField = false;
        }
        else if ($this->showColumns != null && is_array($this->showColumns)) {
            $showSubmitField = in_array('Submitted', $this->showColumns);
        }
        return $showSubmitField;
    }

    /**
     * @param string|array $formName (if array, must be array of string)
     * @param null|string $submitTimeKeyName
     * @return void
     */
    protected function setDataIterator($formName, $submitTimeKeyName = null) {
        $sql = $this->getPivotQuery($formName);
        $this->dataIterator = new CFDBQueryResultIterator();
//        $this->dataIterator->fileColumns = $this->getFileMetaData($formName);

        $queryOptions = array();
        if ($submitTimeKeyName) {
            $queryOptions['submitTimeKeyName'] = $submitTimeKeyName;
        }
        if (!empty($this->rowFilter) && isset($this->options['limit'])) {
            // have data iterator apply the limit if it is not already
            // being applied in SQL directly, which we do when there are
            // no filter constraints.
            $queryOptions['limit'] = $this->options['limit'];
        }
        if (isset($this->options['unbuffered'])) {
            $queryOptions['unbuffered'] = $this->options['unbuffered'];
        }

        $this->dataIterator->query($sql, $this->rowFilter, $queryOptions);
        $this->dataIterator->displayColumns = $this->getColumnsToDisplay($this->dataIterator->columns);
    }

//    protected function &getFileMetaData($formName) {
//        global $wpdb;
//        $tableName = $this->plugin->getSubmitsTableName();
//        $rows = $wpdb->get_results(
//            "select distinct `field_name`
//from `$tableName`
//where `form_name` = '$formName'
//and `file` is not null");
//
//        $fileColumns = array();
//        foreach ($rows as $aRow) {
//            $files[] = $aRow->field_name;
//        }
//        return $fileColumns;
//    }

    /**
     * @param string|array $formName (if array, must be array of string)
     * @param bool $count
     * @return string
     */
    public function &getPivotQuery($formName, $count = false) {
        global $wpdb;
        $tableName = $this->plugin->getSubmitsTableName();

        $formNameClause = '';
        if (is_array($formName)) {
            $formNameClause = 'WHERE `form_name` in ( \'' . implode('\', \'', $formName) . '\' )';
        }
        else if ($formName !== null) {
            $formNameClause =  "WHERE `form_name` = '$formName'";
        }

        $rows = $wpdb->get_results("SELECT DISTINCT `field_name`, `field_order` FROM `$tableName` $formNameClause ORDER BY field_order");
        $fields = array();
        foreach ($rows as $aRow) {
            if (!in_array($aRow->field_name, $fields)) {
                $fields[] = $aRow->field_name;
            }
        }
        $sql = '';
        if ($count) {
            $sql .= 'SELECT count(*) as count FROM (';
        }
        $sql .= "SELECT `submit_time` AS 'Submitted'";
        foreach ($fields as $aCol) {
            $sql .= ",\n max(if(`field_name`='$aCol', `field_value`, null )) AS '$aCol'";
        }
        if (!$count) {
            $sql .= ",\n GROUP_CONCAT(if(`file` is null or length(`file`) = 0, null, `field_name`)) AS 'fields_with_file'";
        }
        $sql .=  "\nFROM `$tableName` \n$formNameClause \nGROUP BY `submit_time` ";
        if ($count) {
            $sql .= ') form';
        }
        else {
            $orderBys = array();
            if ($this->options && isset($this->options['orderby'])) {
                $orderByStrings = explode(',', $this->options['orderby']);
                foreach ($orderByStrings as $anOrderBy) {
                    $anOrderBy = trim($anOrderBy);
                    $ascOrDesc = null;
                    if (strtoupper(substr($anOrderBy, -5)) == ' DESC'){
                        $ascOrDesc = " DESC";
                        $anOrderBy = trim(substr($anOrderBy, 0, -5));
                    }
                    else if (strtoupper(substr($anOrderBy, -4)) == ' ASC'){
                        $ascOrDesc = " ASC";
                        $anOrderBy = trim(substr($anOrderBy, 0, -4));
                    }
                    if ($anOrderBy == 'Submitted') {
                        $anOrderBy = 'submit_time';
                    }
                    if (in_array($anOrderBy, $fields) || $anOrderBy == 'submit_time') {
                        $orderBys[] = '`' . $anOrderBy . '`' . $ascOrDesc;
                    }
                    else {
                        // Want to add a different collation as a different sorting mechanism
                        // Actually doesn't work because MySQL does not allow COLLATE on a select that is a group function
                        $collateIdx = stripos($anOrderBy, ' COLLATE');
                        if ($collateIdx > 0) {
                            $collatedField = substr($anOrderBy, 0, $collateIdx);
                            if (in_array($collatedField, $fields)) {
                                $orderBys[] = '`' . $collatedField . '`' . substr($anOrderBy, $collateIdx) . $ascOrDesc;
                            }
                        }
                    }
                }
            }
            if (empty($orderBys)) {
                $sql .= "\nORDER BY `submit_time` DESC";
            }
            else {
                $sql .= "\nORDER BY ";
                $first = true;
                foreach ($orderBys as $anOrderBy) {
                    if ($first) {
                        $sql .= $anOrderBy;
                        $first = false;
                    }
                    else {
                        $sql .= ', ' . $anOrderBy;
                    }
                }
            }

            if (empty($this->rowFilter) && $this->options && isset($this->options['limit'])) {
                // If no filter constraints and have a limit, add limit to the SQL
                $sql .= "\nLIMIT " . $this->options['limit'];
            }
        }
        //echo $sql; // debug
        return $sql;
    }

    /**
     * @param string|array $formName (if array, must be array of string)
     * @return int
     */
    public function getDBRowCount($formName) {
        global $wpdb;
        $count = 0;
        $rows = $wpdb->get_results($this->getPivotQuery($formName, true));
        foreach ($rows as $aRow) {
            $count = $aRow->count;
            break;
        }
        return $count;
    }
}
