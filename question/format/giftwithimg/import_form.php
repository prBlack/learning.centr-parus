<?php // $Id: format.php,v 1.15.2.3 2007/03/14 14:10:51 thepurpleblob Exp $

//require_once('/../../config.php');
require_once('import_form.php');

// Class which implement second step of questions import process
class giftwithimg_import_form extends question_import_form {  //dlnsk %%38%% %%39%%

    // DO NOT OVERRIDE 'definition' function!
    // Use 'additionalOptions' function for add your own options in 
    // second's step form of import questions

    function additionalOptions(&$form) {
    // Render part of form for getting additional format dependent options
    // Name of each parameter should start from 'fao_' (format's additional option)
    	global $CFG, $COURSE, $QTYPES;

        // Display a header
    	parent::additionalOptions($form);
        
        $dirs = get_directory_list("$CFG->dataroot/{$COURSE->id}", '', true, true, false);
        $newdirs = array();
    	foreach ($dirs as $key => $value) {
            if (strncmp('backupdata', $value, 10) !== 0 and strncmp('moddata', $value, 7) !== 0) {
    	        $newdirs[urlencode($value)] = $value;
    	    }
    	}
        $form->addElement('select', 'fao_dir', get_string('selectimagedirectory', 'qformat_gifwithimg'), array(''=>get_string('none')) + $newdirs);
        $form->setHelpButton('fao_dir', array('giftwithimg_selectimagedirectory', get_string('selectimagedirectory', 'qformat_gifwithimg'), 'quiz'));

        $form->addElement('text', 'fao_fileprefix', get_string('imagefileprefix', 'qformat_gifwithimg'));
        $form->setHelpButton('fao_fileprefix', array('giftwithimg_imagefileprefix', get_string('imagefileprefix', 'qformat_gifwithimg'), 'quiz'));

        $form->addElement('selectyesno', 'fao_relativepath', get_string('userelativepath', 'qformat_gifwithimg'));
        $form->setDefault('fao_relativepath', 1);
        $form->setHelpButton('fao_relativepath', array('giftwithimg_userelativepath', get_string('userelativepath', 'qformat_gifwithimg'), 'quiz'));

        $form->addElement('selectyesno', 'fao_shuflebydefault', get_string('shuflebydefault', 'qformat_gifwithimg'));
        $form->setDefault('fao_shuflebydefault', 1);
        $form->setHelpButton('fao_shuflebydefault', array('giftwithimg_shuflebydefault', get_string('shuflebydefault', 'qformat_gifwithimg'), 'quiz'));

        $numberingoptions = $QTYPES['multichoice']->get_numbering_styles();
        $menu = array();
        foreach ($numberingoptions as $numberingoption) {
            $menu[$numberingoption] = get_string('answernumbering' . $numberingoption, 'qtype_multichoice');
        }
        $form->addElement('select', 'fao_answernumbering', get_string('answernumbering', 'qtype_multichoice'), $menu);
        $form->setDefault('fao_answernumbering', 'none');
        $form->setHelpButton('fao_answernumbering', array('giftwithimg_answernumbering', get_string('answernumbering', 'qtype_multichoice'), 'quiz'));

        // In overriden method we should return 'true'
        return true;
    }

    // Validate all data including your own options
    function validation($data, $files) {
        global $CFG, $COURSE;

        $errors = parent::validation($data, $files);
        // Validate format dependent options //dlnsk %%39%%
        if (!empty($data['choosefile'])) {
            $file = "{$CFG->dataroot}/{$COURSE->id}/{$data['choosefile']}";
            $type = mimeinfo('type', $file);
        } else {
            $file = $this->_upload_manager->files['newfile']['tmp_name'];
            $type = $this->_upload_manager->files['newfile']['type'];
        }
        if (!($type === 'application/zip'
                or $type === 'application/x-zip-compressed'
                or $type === 'application/octet-sream'
                or $type === 'application/octet-stream')) {
            $errors['newfile'] = get_string('unknownfiletype', 'qformat_gifwithimg', $type);//"Unknown file type: $type. ZIP file required!";
        } else {
            include_once("$CFG->libdir/pclzip/pclzip.lib.php");
            $archive = new PclZip(cleardoubleslashes($file));
            if (!$list = $archive->listContent(cleardoubleslashes($file))) {
                $errors['newfile'] = get_string('cannotunzip','quiz').' ('.$archive->errorInfo(true).')';
            } elseif (count($list) > 1 and empty($data['fao_dir'])) {
                $errors['fao_dir'] = get_string('needchoosefolder', 'qformat_gifwithimg');
            }
        }
//        $errors['newfile'] = 'testing';
        return $errors;
    }

}

?>
