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

require_once('ExportBase.php');
require_once('CFDBExport.php');

class ExportToHtmlTable extends ExportBase implements CFDBExport {

    /**
     * @var bool
     */
    static $wroteDefaultHtmlTableStyle = false;

    /**
     * Echo a table of submitted form data
     * @param string $formName
     * @param array $options
     * @return void
     */
    public function export($formName, $options = null) {
        $this->setOptions($options);
        $this->setCommonOptions(true);

        $canDelete = false;
        $useDT = false;
        $printScripts = false;
        $printStyles = false;

        if ($options && is_array($options)) {
            if (isset($options['useDT'])) {
                $useDT = $options['useDT'];
                //$this->htmlTableClass = '';

                if (isset($options['printScripts'])) {
                    $printScripts = $options['printScripts'];
                }

                if (isset($options['printStyles'])) {
                    $printStyles = $options['printStyles'];
                }
            }

            if (isset($options['canDelete'])) {
                $canDelete = $options['canDelete'];
            }
        }

        // Security Check
        if (!$this->isAuthorized()) {
            $this->assertSecurityErrorMessage();
            return;
        }

        // Headers
        $this->echoHeaders('Content-Type: text/html; charset=UTF-8');

        if ($this->isFromShortCode) {
            ob_start();
        }
        else {
            if ($printScripts) {
                $pluginUrl = plugins_url('/', __FILE__);
                wp_enqueue_script('datatables', $pluginUrl . 'DataTables/media/js/jquery.dataTables.min.js', array('jquery'));
                wp_print_scripts('datatables');
            }
            if ($printStyles) {
                $pluginUrl = plugins_url('/', __FILE__);
                wp_enqueue_style('datatables-demo', $pluginUrl .'DataTables/media/css/demo_table.css');
                wp_enqueue_style('jquery-ui.css', $pluginUrl . 'jquery-ui/jquery-ui.css');
                wp_print_styles(array('jquery-ui.css', 'datatables-demo'));
            }
        }

        // Query DB for the data for that form
        $submitTimeKeyName = 'Submit_Time_Key';
        $this->setDataIterator($formName, $submitTimeKeyName);

        if ($useDT) {
            $dtJsOptions = isset($options['dt_options']) ? $options['dt_options'] : false;
            if (!$dtJsOptions) {
                $dtJsOptions = '"bJQueryUI": true, "aaSorting": []';
                $i18nUrl = $this->plugin->getDataTableTranslationUrl();
                if ($i18nUrl) {
                    $dtJsOptions = $dtJsOptions . ", \"oLanguage\": { \"sUrl\":  \"$i18nUrl\" }";
                }
            }
            ?>
            <script type="text/javascript" language="Javascript">
                jQuery(document).ready(function() {
                    jQuery('#<?php echo $this->htmlTableId ?>').dataTable({
                        <?php echo $dtJsOptions ?> })
                });
            </script>
            <?php
        }

        if ($this->htmlTableClass == $this->defaultTableClass && !ExportToHtmlTable::$wroteDefaultHtmlTableStyle) {
            ?>
            <style type="text/css">
                table.<?php echo $this->defaultTableClass ?> {
                    margin-top: 1em;
                    border-spacing: 0;
                    border: 0 solid gray;
                    font-size: x-small;
                }

                br {
                    <?php /* Thanks to Alberto for this style which means that in Excel IQY all the text will
                     be in the same cell, not broken into different cells */ ?>
                    mso-data-placement: same-cell;
                }

                table.<?php echo $this->defaultTableClass ?> th {
                    padding: 5px;
                    border: 1px solid gray;
                }

                table.<?php echo $this->defaultTableClass ?> th > td {
                    font-size: x-small;
                    background-color: #E8E8E8;
                }

                table.<?php echo $this->defaultTableClass ?> tbody td {
                    padding: 5px;
                    border: 1px solid gray;
                    font-size: x-small;
                }

                table.<?php echo $this->defaultTableClass ?> tbody td > div {
                    max-height: 100px;
                    overflow: auto;
                }
            </style>
            <?php
            ExportToHtmlTable::$wroteDefaultHtmlTableStyle = true;
        }

        if ($this->style) {
            ?>
            <style type="text/css">
                <?php echo $this->style ?>
            </style>
            <?php
        }
        ?>

        <table <?php if ($this->htmlTableId) echo "id=\"$this->htmlTableId\" "; if ($this->htmlTableClass) echo "class=\"$this->htmlTableClass\"" ?> >
            <thead>
            <?php
            if (isset($this->options['header']) && $this->options['header'] != 'true') {
               // do not output column headers
            }
            else  {
            ?>
            <tr>
            <?php if ($canDelete) { ?>
            <th>
                <button id="delete" name="delete" onclick="this.form.submit()"><?php _e('Delete', 'contact-form-7-to-database-extension')?></button>
            </th>
            <?php

            }
            foreach ($this->dataIterator->displayColumns as $aCol) {
                printf('<th title="%s"><div id="%s,%s">%s</div></th>', $aCol, $formName, $aCol, $aCol);
            }
            ?>
            </tr>
            <?php
            } ?>
            </thead>
            <tbody>
            <?php
            $showLineBreaks = $this->plugin->getOption('ShowLineBreaksInDataTable');
            $showLineBreaks = 'false' != $showLineBreaks;
            while ($this->dataIterator->nextRow()) {
                $submitKey = $this->dataIterator->row[$submitTimeKeyName];
                ?>
                <tr>
                <?php if ($canDelete) { // Put in the delete checkbox ?>
                    <td align="center">
                        <input type="checkbox" name="<?php echo $submitKey ?>" value="row"/>
                    </td>
                <?php

                }

                $fields_with_file = null;
                if (isset($this->dataIterator->row['fields_with_file']) && $this->dataIterator->row['fields_with_file'] != null) {
                    $fields_with_file = explode(',', $this->dataIterator->row['fields_with_file']);
                }
                foreach ($this->dataIterator->displayColumns as $aCol) {
                    $cell = $this->rawValueToPresentationValue(
                        $this->dataIterator->row[$aCol],
                        $showLineBreaks,
                        ($fields_with_file && in_array($aCol, $fields_with_file)),
                        $this->dataIterator->row[$submitTimeKeyName],
                        $formName,
                        $aCol);

                    // NOTE: the ID field is used to identify the cell when an edit happens and we save that to the server
                    printf('<td title="%s"><div id="%s,%s">%s</div></td>', $aCol, $submitKey, $aCol, $cell);
                }
                ?></tr><?php

            } ?>
            </tbody>
        </table>
        <?php

        if ($this->isFromShortCode) {
            // If called from a shortcode, need to return the text,
            // otherwise it can appear out of order on the page
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        }
    }

    public function &rawValueToPresentationValue(&$value, $showLineBreaks, $isUrl, &$submitTimeKey, &$formName, &$fieldName) {
        $value = htmlentities($value, null, 'UTF-8'); // no HTML injection
        if ($showLineBreaks) {
            $value = str_replace("\r\n", '<br/>', $value); // preserve DOS line breaks
            $value = str_replace("\n", '<br/>', $value); // preserve UNIX line breaks
        }
        if ($isUrl) {
            $fileUrl = $this->plugin->getFileUrl($submitTimeKey, $formName, $fieldName);
            $value = "<a href=\"$fileUrl\">$value</a>";
        }

        return $value;
    }
}

