<?php

/*
    "Contact Form to Database" Copyright (C) 2011-2015 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This file is part of Contact Form to Database.

    Contact Form to Database is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Contact Form to Database is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database.
    If not, see <http://www.gnu.org/licenses/>.
*/

class CFDBIntegrationWRContactForm {

    /**
     * @var CF7DBPlugin
     */
    var $plugin;

    /**
     * @param $plugin CF7DBPlugin
     */
    function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function registerHooks() {
        add_action('wr_contactform_before_save_form', array(&$this, 'saveFormData'), 10, 7);
    }

    /**
     * @param $dataForms array
     * @param $postID array
     * @param $post array
     * @param $submissionsData array
     * @param $dataContentEmail array
     * @param $nameFileByIdentifier array
     * @param $requiredField array
     * @param $fileAttach array
     * @return bool
     */
    public function saveFormData($dataForms, $postID, $post, $submissionsData, $dataContentEmail,
                                 $nameFileByIdentifier, $requiredField, $fileAttach) {

        try {
            $data = $this->convertData($dataForms, $postID, $post, $submissionsData, $dataContentEmail,
                    $nameFileByIdentifier, $requiredField, $fileAttach);
            return $this->plugin->saveFormData($data);
        } catch (Exception $ex) {
            $this->plugin->getErrorLog()->logException($ex);
        }
        return true;
    }


    public function convertData($dataForms, $postID, $post, $submissionsData, $dataContentEmail,
                                $nameFileByIdentifier, $requiredField, $fileAttach) {

        $postedData = array();
        $uploadFiles = array();

        foreach ($dataContentEmail as $fieldKey => $fieldValue) {
            $fieldName = $nameFileByIdentifier[$fieldKey];

            if (strpos($fieldKey, 'file_upload_') === 0) {
                // Handle upload files
                $href = array();
                preg_match('#<a href=\"([^\"]*)/wp-content/uploads/wr_contactform/([^\"]*)\">(.*)</a>#iU', $fieldValue, $href);
                if (count($href) >= 3) {
//                    [0] => <a href="http://site.com/wp-content/uploads/wr_contactform/2015/01/icon-50x50.png">icon-50x50.png</a>
//                    [1] => http://site.com
//                    [2] => 2015/01/icon-50x50.png
//                    [3] => icon-50x50.png
                    $filePath = dirname(dirname(dirname(__FILE__))) . '/uploads/wr_contactform/' . $href[2];
                    $uploadFiles[$fieldName] = $filePath;
                }
            }
            $fieldValue = trim(preg_replace('#<[^>]+>#', ' ', $fieldValue));
            $postedData[$fieldName] = $fieldValue;
        }

        $data = (object)array(
                'title' => get_the_title($postID),
                'posted_data' => $postedData,
                'uploaded_files' => $uploadFiles);
        return $data;
    }

}