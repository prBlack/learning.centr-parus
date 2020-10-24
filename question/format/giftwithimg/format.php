<?php // $Id: format.php,v 1.24.2.5 2008/05/01 12:10:13 thepurpleblob Exp $
//
///////////////////////////////////////////////////////////////
// The GIFT import filter was designed as an easy to use method 
// for teachers writing questions as a text file. It supports most
// question types and the missing word format.
//
// Multiple Choice / Missing Word
//     Who's buried in Grant's tomb?{~Grant ~Jefferson =no one}
//     Grant is {~buried =entombed ~living} in Grant's tomb.
// True-False:
//     Grant is buried in Grant's tomb.{FALSE}
// Short-Answer.
//     Who's buried in Grant's tomb?{=no one =nobody}
// Numerical
//     When was Ulysses S. Grant born?{#1822:5}
// Matching
//     Match the following countries with their corresponding
//     capitals.{=Canada->Ottawa =Italy->Rome =Japan->Tokyo}
//
// Comment lines start with a double backslash (//). 
// Optional question names are enclosed in double colon(::). 
// Answer feedback is indicated with hash mark (#).
// Percentage answer weights immediately follow the tilde (for
// multiple choice) or equal sign (for short answer and numerical),
// and are enclosed in percent signs (% %). See docs and examples.txt for more.
// 
// This filter was written through the collaboration of numerous 
// members of the Moodle community. It was originally based on 
// the missingword format, which included code from Thomas Robb
// and others. Paul Tsuchido Shew wrote this filter in December 2003.
//////////////////////////////////////////////////////////////////////////
// Based on default.php, included by ../import.php
/**
 * @package questionbank
 * @subpackage importexport
 */
class qformat_giftwithimg extends qformat_default {

    function provide_import() {
        return true;
    }

    function provide_export() {
        return false;
    }

    /**
     * Set additional options depended of format
     * @param array aditional options
     */
    function setOptions( $options ) {
        parent::setOptions($options);
        @$this->option['fileprefix'] = clean_filename($this->option['fileprefix']);
    }

    function answerweightparser(&$answer) {
        $answer = substr($answer, 1);                        // removes initial %
        $end_position  = strpos($answer, "%");
        $answer_weight = substr($answer, 0, $end_position);  // gets weight as integer
        $answer_weight = $answer_weight/100;                 // converts to percent
        $answer = substr($answer, $end_position+1);          // removes comment from answer
        return $answer_weight;
    }


    function commentparser(&$answer) {
        if (strpos($answer,"#") > 0){
            $hashpos = strpos($answer,"#");
            $comment = substr($answer, $hashpos+1);
            $comment = addslashes(trim($this->escapedchar_post($comment)));
            $comment = preg_replace('/\n\s?/', "<br />\n", $comment);
            $answer  = substr($answer, 0, $hashpos);
        } else {
            $comment = " ";
        }
        return $comment;
    }

    function split_truefalse_comment($comment){
        // splits up comment around # marks
        // returns an array of true/false feedback
        $bits = explode('#',$comment);
        $feedback = array('wrong' => $bits[0]);
        if (count($bits) >= 2) {
            $feedback['right'] = $bits[1];
        } else {
            $feedback['right'] = '';
        }
        return $feedback;
    }
    
    function escapedchar_pre($string) {
        //Replaces escaped control characters with a placeholder BEFORE processing
        
        $escapedcharacters = array("\\:",    "\\#",    "\\=",    "\\{",    "\\}",    "\\~",    "\\n"   );  //dlnsk
        $placeholders      = array("&&058;", "&&035;", "&&061;", "&&123;", "&&125;", "&&126;", "&&010;" );  //dlnsk

        $string = str_replace("\\\\", "&&092;", $string);
        $string = str_replace($escapedcharacters, $placeholders, $string);
        $string = str_replace("&&092;", "\\", $string);
        return $string;
    }

    function escapedchar_post($string) {
		global $CFG;
        //Replaces placeholders with corresponding character AFTER processing is done
        $placeholders = array("&&058;", "&&035;", "&&061;", "&&123;", "&&125;", "&&126;"); //dlnsk
        $characters   = array(":",      "#",      "=",      "{",      "}",      "~",    ); //dlnsk
        $string = str_replace($placeholders, $characters, $string);
        $string = preg_replace('/&&010;\s?/', "\n", $string);

        //dlnsk %%38%%
        $slash = strpos($CFG->wwwroot, '/', 7);
        $path = ($slash === false) ? '' : substr($CFG->wwwroot, $slash); // for domain like http://server.com/moodle
        $www = $this->option['relativepath'] ? $path : $CFG->wwwroot;
    	$replacement = " <img src=\"$www/file.php/{$this->course->id}/".urldecode($this->option['dir']).'/'.$this->option['fileprefix'].'\1" alt=""> ';
        $string = ereg_replace('<<([[:alnum:].-]+\.[[:alnum:].-]+)>>', $replacement, $string);
        
    	return $string;
    }

    function check_answer_count( $min, $answers, $text ) {
        $countanswers = count($answers);
        if ($countanswers < $min) {
            $importminerror = get_string( 'importminerror', 'quiz' );
            $this->error( $importminerror, $text );
            return false;
        }

        return true;
    }

    function readdata($filename) { //dlnsk %%38%%
    /// Returns complete file with an array, one item per line

        if (is_readable($filename)) {
            $filearray = file($filename);

            /// Check for Macintosh OS line returns (ie file on one line), and fix
            if (ereg("\r", $filearray[0]) AND !ereg("\n", $filearray[0])) {
                return explode("\r", $filearray[0]);
            } else {
                return $filearray;
            }
        }
        return false;
    }

    function create_tempdir() { //dlnsk %%38%%
	 	global $CFG, $USER;
	 	
	    $temp_dir = "{$CFG->dataroot}/temp/questions";
	    if (!file_exists($temp_dir)) {
		    if (!file_exists("{$CFG->dataroot}/temp")) {
		        mkdir( "{$CFG->dataroot}/temp", $CFG->directorypermissions );
		    }
		    if (!file_exists( $temp_dir )) {
		        mkdir( $temp_dir, $CFG->directorypermissions );
		    }
	    }
	    $temp_dir .= '/'.$USER->id.'_'.time();
        mkdir( $temp_dir, $CFG->directorypermissions );
        return $temp_dir;
    }
    
    function importprocess() { //dlnsk %%38%%
    /// Processes a given file.  There's probably little need to change this
    	global $CFG;
    	
        $target_dir = "$CFG->dataroot/{$this->course->id}/".urldecode($this->option['dir']);
        $temp_dir = $this->create_tempdir();

        include_once("$CFG->libdir/pclzip/pclzip.lib.php");
        $archive = new PclZip(cleardoubleslashes($this->filename));
        if (!$list = $archive->extract(PCLZIP_OPT_PATH, $temp_dir,
                                       PCLZIP_CB_PRE_EXTRACT, 'unzip_cleanfilename',
                                       PCLZIP_OPT_EXTRACT_DIR_RESTRICTION, $temp_dir)) {
            notice(get_string('cannotunzip','quiz').' ('.$archive->errorInfo(true).')');
            return false;
        }
        $files = get_directory_list($temp_dir, array('gift_format.txt'), false);
//        if ($this->option['dir'] === '0' && count($files) > 0) {
//            notify( get_string('directorynotspecified','quiz') );
//            return false;
//        }
        foreach ($files as $file) {
            @unlink("$target_dir/{$this->option['fileprefix']}{$file}");
            rename($temp_dir.'/'.$file, "$target_dir/{$this->option['fileprefix']}{$file}");
        }
        $this->filename = $temp_dir.'/gift_format.txt';
    		
    	$result = parent::importprocess();
  		fulldelete($temp_dir);
        
    	return $result;
    }

    function readquestions($lines) { //dlnsk %%38%%
    /// Parses an array of lines into an array of questions, 
    /// where each item is a question object as defined by 
    /// readquestion().   Questions are defined as anything 
    /// between blank lines.
		global $CFG;
		
        $questions = array();
        $currentquestion = array();
        
//        $www = isset($this->option['relativepath']) ? '' : $CFG->wwwroot;
//    	$replacement = "<img src=\"$www/file.php/{$this->course->id}/".urldecode($this->option['dir']).'/'.$this->option['fileprefix'].'\1" alt="">';

	   	foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                if (!empty($currentquestion)) {
                    if ($question = $this->readquestion($currentquestion)) {
                        $questions[] = $question;
                    }
                    $currentquestion = array();
                }
            } else {
                //$currentquestion[] = ereg_replace('<<([[:alnum:].-]+\.[[:alnum:].-]+)>>', $replacement, $line);
                $currentquestion[] = $line;
            }
        }

        if (!empty($currentquestion)) {  // There may be a final question
            if ($question = $this->readquestion($currentquestion)) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    function readquestion($lines) {
    // Given an array of lines known to define a question in this format, this function
    // converts it into a question object suitable for processing and insertion into Moodle.

    	//Question's options tags
    	$tags = array('shuffle', 'no_shuffle');
        $formats = array('markdown', 'html', 'plain');
    
        $question = $this->defaultquestion();
        $comment = NULL;
        // define replaced by simple assignment, stop redefine notices
        $gift_answerweight_regex = "^%\-*([0-9]{1,2})\.?([0-9]*)%";        

        $a = strpos(substr($lines[0], 0, 5), '//'); //removing unicode signature (dlnsk)
        if (is_numeric($a)) {
        	$lines[0] = substr($lines[0], $a);
        }
        // REMOVED COMMENTED LINES and IMPLODE
        foreach ($lines as $key => $line) {
            $line = trim($line);
            if (substr($line, 0, 2) == "//") {
                $lines[$key] = " ";
            }
        }

        $text = trim(implode(" ", $lines));

        if ($text == "") {
            return false;
        }

        // Substitute escaped control characters with placeholders
        $text = $this->escapedchar_pre($text);

        // Look for category modifier
        if (ereg( '^\$CATEGORY:', $text)) {
            // $newcategory = $matches[1];
            $newcategory = trim(substr( $text, 10 ));

            // build fake question to contain category
            $question->qtype = 'category';
            $question->category = $newcategory;
            return $question;
        }


        // FIND ANSWER section
        // no answer means its a description
        $answerstart = strpos($text, "{");
        $answerfinish = strpos($text, "}");

        $description = false;
        if (($answerstart === false) and ($answerfinish === false)) {
            $description = true;
            $answertext = '';
            $answerlength = 0;
        }
        elseif (!(($answerstart !== false) and ($answerfinish !== false))) {
            $this->error( get_string( 'braceerror', 'quiz' ), $text );
            return false;
        }
        else {
            $answerlength = $answerfinish - $answerstart;
            $answertext = trim(substr($text, $answerstart + 1, $answerlength - 1));
        }

        // Format QUESTION TEXT without answer, inserting "_____" as necessary
        if ($description) {
            $questiontext = $text;
        }
        elseif ($answerfinish == strlen($text)-1) { //dlnsk
            // no blank line if answers follow question, outside of closing punctuation
            $questiontext = substr_replace($text, '', $answerstart, $answerlength+1);
        } else {
            // inserts blank line for missing word format
            $questiontext = substr_replace($text, '_____', $answerstart, $answerlength+1);
        }
        $questiontext = trim(strtr($questiontext, array('{'=>'', '}'=>''))); //for question with blank word (dlnsk)
        
        // Getting generalfeedback if exist (dlnsk)
        $feedstart = strpos($questiontext, "#");
        if (is_numeric($feedstart)) {
            $question->generalfeedback = addslashes(trim($this->escapedchar_post(substr($questiontext, $feedstart+1))));
            $questiontext = trim(substr($questiontext, 0, $feedstart));
        }

        // get questiontext format from questiontext
        $questiontextformat = 0;
        $shuffle = false;
        $matches = array();
        if (preg_match_all('/((\[([a-z_]+?|[0-9]+?)\])\s*)+/', $questiontext, $matches)) {
            $options = preg_split('@[\[\]\s]+@', $matches[0][0], -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($options)) {
                if ($qtformat = array_pop(array_intersect($options, $formats))) {
                    $questiontextformat = text_format_name($qtformat);
                }
                $options = array_diff($options, $formats);

                $shuffle = array_pop(array_intersect($options, $tags));
                $options = array_diff($options, $tags);

                if ($grade = array_pop($options)) {
                    $question->defaultgrade = is_numeric($grade) ? $grade : 1;
                }
                $questiontext = substr($questiontext, strlen($matches[0][0]));
            }
        }
        $question->questiontextformat = $questiontextformat;

        // QUESTION NAME parser
        // if name of question start with ';;' then it don't remove from question text (dlnsk %%40%%)
        $questiontext = trim($questiontext);
        if (substr($questiontext, 0, 2) == "::" or substr($questiontext, 0, 2) == ";;") {
            $prefix = substr($questiontext, 0, 2);
            $questiontext = substr($questiontext, 2);

            $namefinish = strpos($questiontext, "::");
            if ($namefinish === false) {
                $question->name = false;
                // name will be assigned after processing question text below
            } else {
                $questionname = substr($questiontext, 0, $namefinish);
                $question->name = addslashes(trim($this->escapedchar_post($questionname)));
                if ($prefix == ';;') {
                    $questiontext = trim(substr($questiontext, 0, $namefinish).substr($questiontext, $namefinish+2)); // question name stay as path of question
                } else {
                    $questiontext = trim(substr($questiontext, $namefinish+2)); // Remove name from text
                }
            }
        } else {
            $question->name = false;
        }
        $question->questiontext = addslashes(trim($this->escapedchar_post($questiontext)));

        // set question name if not already set
        if ($question->name === false) {
        	$question->name = $question->questiontext;
        }
        // cutting long question text from start to '?' or make name 100 chars length (dlnsk %%40%%)
        if (false and $question->name === false) { //ZDE editor bug (dlnsk)
        	$question->name = ereg_replace('<<.*?>>', '', $question->name); //removing images
        	$question->name = ereg_replace('<.*?>', '', $question->name); //removing tags
//            if ($namefinish = strpos($question->name, '<') === false) {
//            	$namefinish = strpos($question->name, '?');
//            }
//            if ($namefinish and $namefinish < 100) {
//                $question->name = substr($question->name, 0, $namefinish+1);
//            } else {
//                $question->name = substr($question->name, 0, 99).'...';
//            }
        }

        // ensure name is not longer than 250 characters
//        $question->name = shorten_text( $question->name, 100 );
        $question->name = shorten_text(strip_tags($question->name), 100);

        // determine QUESTION TYPE
        $question->qtype = NULL;

        // give plugins first try
        // plugins must promise not to intercept standard qtypes
        // MDL-12346, this could be called from lesson mod which has its own base class =(
        if (method_exists($this, 'try_importing_using_qtypes') && ($try_question = $this->try_importing_using_qtypes( $lines, $question, $answertext ))) {
            return $try_question;
        }

        if ($description) {
            $question->qtype = DESCRIPTION;
        }
        elseif ($answertext == '') {
            $question->qtype = ESSAY;
        }
        elseif ($answertext{0} == "#"){
            $question->qtype = NUMERICAL;

        } elseif (strpos($answertext, "~") !== false)  {
            // only Multiplechoice questions contain tilde ~
            $question->qtype = MULTICHOICE;
    
        } elseif (strpos($answertext, "=")  !== false 
                && strpos($answertext, "->") !== false) {
            // only Matching contains both = and ->
            $question->qtype = MATCH;

        } else { // either TRUEFALSE or SHORTANSWER
    
            // TRUEFALSE question check
            $truefalse_check = $answertext;
            if (strpos($answertext,"#") > 0){ 
                // strip comments to check for TrueFalse question
                $truefalse_check = trim(substr($answertext, 0, strpos($answertext,"#")));
            }

            $valid_tf_answers = array("T", "TRUE", "F", "FALSE");
            if (in_array($truefalse_check, $valid_tf_answers)) {
                $question->qtype = TRUEFALSE;

            } else { // Must be SHORTANSWER
                    $question->qtype = SHORTANSWER;
            }
        }

        if (!isset($question->qtype)) {
            $giftqtypenotset = get_string('giftqtypenotset','quiz');
            $this->error( $giftqtypenotset, $text );
            return false;
        }
        if (!$this->option['shuflebydefault']) { // dlnsk %%51%%
        	$question->shuffleanswers = 0;
        }

        switch ($question->qtype) {
            case DESCRIPTION:
                $question->defaultgrade = 0;
                $question->length = 0;
                return $question;
                break;
            case ESSAY:
                $question->feedback = '';
                $question->fraction = 0;
                return $question;
                break;
            case MULTICHOICE:
                //when question imported from Word-template right answer may marked by ~%100% (dlnsk %%41%%)
                if (strpos($answertext,"=") === false and strpos($answertext,"~%100%") === false) { 
                    $question->single = 0;   // multiple answers are enabled if no single answer is 100% correct                        
                } else {
                    $question->single = 1;   // only one answer allowed (the default)
                }

            	if ($shuffle == 'no_shuffle') { // dlnsk %%51%%
            		$question->shuffleanswers = 0;
            	}
            	if ($shuffle == 'shuffle') { // dlnsk %%51%%
            		$question->shuffleanswers = 1;
            	}
                $question->answernumbering = $this->option['answernumbering'];

            	$answertext = str_replace("=", "~=", $answertext);
                $answers = explode("~", $answertext);
                if (isset($answers[0])) {
                    $answers[0] = trim($answers[0]);
                }
                if (empty($answers[0])) {
                    array_shift($answers);
                }
    
                $countanswers = count($answers);
                
                if (!$this->check_answer_count( 2,$answers,$text )) {
                    return false;
                    break;
                }

                foreach ($answers as $key => $answer) {
                    $answer = trim($answer);

                    // determine answer weight
                    if ($answer[0] == "=") {
                        $answer_weight = 1;
                        $answer = substr($answer, 1);
    
                    } elseif (ereg($gift_answerweight_regex, $answer)) {    // check for properly formatted answer weight
                        $answer_weight = $this->answerweightparser($answer);
                    
                    } else {     //default, i.e., wrong anwer
                        $answer_weight = 0;
                    }
                    $question->fraction[$key] = $answer_weight;
                    $question->feedback[$key] = $this->commentparser($answer); // commentparser also removes comment from $answer
                    $question->answer[$key]   = addslashes($this->escapedchar_post($answer));
                    $question->correctfeedback = '';
                    $question->partiallycorrectfeedback = '';
                    $question->incorrectfeedback = '';
                }  // end foreach answer
    
                //$question->defaultgrade = 1;
                //$question->image = "";   // No images with this format
                return $question;
                break;

            case MATCH:
            	// shuffleanswers not used? Always shuffle? (dlnsk ???)
            	if ($shuffle === 'no_shuffle') { // dlnsk %%51%%
            		$question->shuffleanswers = 0;
            	}
            	if ($shuffle === 'shuffle') { // dlnsk %%51%%
            		$question->shuffleanswers = 1;
            	}

                $answers = explode("=", $answertext);
                if (isset($answers[0])) {
                    $answers[0] = trim($answers[0]);
                }
                if (empty($answers[0])) {
                    array_shift($answers);
                }
    
                if (!$this->check_answer_count( 2,$answers,$text )) {
                    return false;
                    break;
                }
    
                foreach ($answers as $key => $answer) {
                    $answer = trim($answer);
                    if (strpos($answer, "->") === false) {
                        $giftmatchingformat = get_string('giftmatchingformat','quiz');
                        $this->error($giftmatchingformat, $answer );
                        return false;
                        break 2;
                    }

                    $marker = strpos($answer,"->");
                    $question->subquestions[$key] = addslashes(trim($this->escapedchar_post(substr($answer, 0, $marker))));
                    $question->subanswers[$key]   = addslashes(trim($this->escapedchar_post(substr($answer, $marker+2))));

                }  // end foreach answer
    
                return $question;
                break;
            
            case TRUEFALSE:
                $answer = $answertext;
                $comment = $this->commentparser($answer); // commentparser also removes comment from $answer
                $feedback = $this->split_truefalse_comment($comment);

                if ($answer == "T" OR $answer == "TRUE") {
                    $question->answer = 1;
                    $question->feedbacktrue = $feedback['right'];
                    $question->feedbackfalse = $feedback['wrong'];
                } else {
                    $question->answer = 0;
                    $question->feedbackfalse = $feedback['right'];
                    $question->feedbacktrue = $feedback['wrong'];
                }

                $question->penalty = 1;
                $question->correctanswer = $question->answer;

                return $question;
                break;
                
            case SHORTANSWER:
                // SHORTANSWER Question
                $answers = explode("=", $answertext);
                if (isset($answers[0])) {
                    $answers[0] = trim($answers[0]);
                }
                if (empty($answers[0])) {
                    array_shift($answers);
                }
    
                if (!$this->check_answer_count( 1,$answers,$text )) {
                    return false;
                    break;
                }

                foreach ($answers as $key => $answer) {
                    $answer = trim($answer);

                    // Answer Weight
                    if (ereg($gift_answerweight_regex, $answer)) {    // check for properly formatted answer weight
                        $answer_weight = $this->answerweightparser($answer);
                    } else {     //default, i.e., full-credit anwer
                        $answer_weight = 1;
                    }
                    $question->fraction[$key] = $answer_weight;
                    $question->feedback[$key] = $this->commentparser($answer); //commentparser also removes comment from $answer
                    $question->answer[$key]   = addslashes($this->escapedchar_post($answer));
                }     // end foreach

                //$question->usecase = 0;  // Ignore case
                //$question->defaultgrade = 1;
                //$question->image = "";   // No images with this format
                return $question;
                break;

            case NUMERICAL:
                // Note similarities to ShortAnswer
                $answertext = substr($answertext, 1); // remove leading "#"

                // If there is feedback for a wrong answer, store it for now.
                $split = preg_split('/~((?!%)|(?=%-))/', $answertext);
                $answertext = strtr($split[0], '~', '=');
                $wrongfeedback = isset($split[1]) ? $split[1] : '';

                $answers = explode("=", $answertext);
                if (isset($answers[0])) {
                    $answers[0] = trim($answers[0]);
                }
                if (empty($answers[0])) {
                    array_shift($answers);
                }
    
                if (count($answers) == 0) {
                    // invalid question
                    $giftnonumericalanswers = get_string('giftnonumericalanswers','quiz');
                    $this->error( $giftnonumericalanswers, $text );
                    return false;
                    break;
                }

                foreach ($answers as $key => $answer) {
                    $answer = trim($answer);

                    // Answer weight
                    if (ereg($gift_answerweight_regex, $answer)) {    // check for properly formatted answer weight
                        $answer_weight = $this->answerweightparser($answer);
                    } else {     //default, i.e., full-credit anwer
                        $answer_weight = 1;
                    }
                    $question->fraction[$key] = $answer_weight;
                    $question->feedback[$key] = $this->commentparser($answer); //commentparser also removes comment from $answer

                    //Calculate Answer and Min/Max values
                    $answer = str_replace(array('±', '&plusmn;'), array(':', ':'), $answer);
                    if (strpos($answer,"..") > 0) { // optional [min]..[max] format
                        $marker = strpos($answer,"..");
                        $max = trim(substr($answer, $marker+2));
                        $min = trim(substr($answer, 0, $marker));
                        $ans = ($max + $min)/2;
                        $tol = $max - $ans;
                    } elseif (strpos($answer,":") > 0){ // standard [answer]:[errormargin] format
                        $marker = strpos($answer,":");
                        $tol = trim(substr($answer, $marker+1));
                        $ans = trim(substr($answer, 0, $marker));
                    } else { // only one valid answer (zero errormargin)
                        $tol = 0;
                        $ans = trim($answer);
                    }
    
                    if (!(is_numeric($ans) || $ans = '*') || !is_numeric($tol)) {
                            $errornotnumbers = get_string( 'errornotnumbers' );
                            $this->error( $errornotnumbers, $text );
                        return false;
                        break;
                    }
                    
                    // store results
                    $question->answer[$key] = $ans;
                    $question->tolerance[$key] = $tol;
                } // end foreach

                if ($wrongfeedback) {
                    $key += 1;
                    $question->fraction[$key] = 0;
                    $question->feedback[$key] = $this->commentparser($wrongfeedback);
                    $question->answer[$key] = '*';
                    $question->tolerance[$key] = '0';
                }

                return $question;
                break;

                default:
                    $giftnovalidquestion = get_string('giftnovalidquestion','quiz');
                    $this->error( $giftnovalidquestion, $text );
                return false;
                break;                
        
        } // end switch ($question->qtype)

    }    // end function readquestion($lines)

function repchar( $text, $format=0 ) {
    // escapes 'reserved' characters # = ~ { ) : and removes new lines
    // also pushes text through format routine
    $reserved = array( '#', '=', '~', '{', '}', ':', "\n","\r");
    $escaped =  array( '\#','\=','\~','\{','\}','\:','\n',''  ); //dlnsk

    $newtext = str_replace( $reserved, $escaped, $text ); 
    $format = 0; // turn this off for now
    if ($format) {
        $newtext = format_text( $format );
    }
    return $newtext;
    }

function writequestion( $question ) {
    // turns question into string
    // question reflects database fields for general question and specific to type

    // initial string;
    $expout = "";

    // add comment
    $expout .= "// question: $question->id  name: $question->name \n";

    // get  question text format
    $textformat = $question->questiontextformat;
    $tfname = "";
    if ($textformat!=FORMAT_MOODLE) {
        $tfname = text_format_name( (int)$textformat );
        $tfname = "[$tfname]";
    }

    // output depends on question type
    switch($question->qtype) {
    case 'category':
        // not a real question, used to insert category switch
        $expout .= "\$CATEGORY: $question->category\n";    
        break;
    case DESCRIPTION:
        $expout .= '::'.$this->repchar($question->name).'::';
        $expout .= $tfname;
        $expout .= $this->repchar( $question->questiontext, $textformat);
        break;
    case ESSAY:
        $expout .= '::'.$this->repchar($question->name).'::';
        $expout .= $tfname;
        $expout .= $this->repchar( $question->questiontext, $textformat);
        $expout .= "{}\n";
        break;
    case TRUEFALSE:
        $trueanswer = $question->options->answers[$question->options->trueanswer];
        $falseanswer = $question->options->answers[$question->options->falseanswer];
        if ($trueanswer->fraction == 1) {
            $answertext = 'TRUE';
            $right_feedback = $trueanswer->feedback;
            $wrong_feedback = $falseanswer->feedback;
        } else {
            $answertext = 'FALSE';
            $right_feedback = $falseanswer->feedback;
            $wrong_feedback = $trueanswer->feedback;
        }

        $wrong_feedback = $this->repchar($wrong_feedback);
        $right_feedback = $this->repchar($right_feedback);
        $expout .= "::".$this->repchar($question->name)."::".$tfname.$this->repchar( $question->questiontext,$textformat )."{".$this->repchar( $answertext );
        if ($wrong_feedback) {
            $expout .= "#" . $wrong_feedback;
        } else if ($right_feedback) {
            $expout .= "#";
        }
        if ($right_feedback) {
            $expout .= "#" . $right_feedback;
        }
        $expout .= "}\n";
        break;
    case MULTICHOICE:
        $expout .= "::".$this->repchar($question->name)."::".$tfname.$this->repchar( $question->questiontext, $textformat )."{\n";
        foreach($question->options->answers as $answer) {
            if ($answer->fraction==1) {
                $answertext = '=';
            }
            elseif ($answer->fraction==0) {
                $answertext = '~';
            }
            else {
                $export_weight = $answer->fraction*100;
                $answertext = "~%$export_weight%";
            }
            $expout .= "\t".$answertext.$this->repchar( $answer->answer );
            if ($answer->feedback!="") {
                $expout .= "#".$this->repchar( $answer->feedback );
            }
            $expout .= "\n";
        }
        $expout .= "}\n";
        break;
    case SHORTANSWER:
        $expout .= "::".$this->repchar($question->name)."::".$tfname.$this->repchar( $question->questiontext, $textformat )."{\n";
        foreach($question->options->answers as $answer) {
            $weight = 100 * $answer->fraction;
            $expout .= "\t=%".$weight."%".$this->repchar( $answer->answer )."#".$this->repchar( $answer->feedback )."\n";
        }
        $expout .= "}\n";
        break;
    case NUMERICAL:
        $expout .= "::".$this->repchar($question->name)."::".$tfname.$this->repchar( $question->questiontext, $textformat )."{#\n";
        foreach ($question->options->answers as $answer) {
            if ($answer->answer != '') {
                $percentage = '';
                if ($answer->fraction < 1) {
                    $pval = $answer->fraction * 100;
                    $percentage = "%$pval%";
                }
                $expout .= "\t=$percentage".$answer->answer.":".(float)$answer->tolerance."#".$this->repchar( $answer->feedback )."\n";
            } else {
                $expout .= "\t~#".$this->repchar( $answer->feedback )."\n";
            }
        }
        $expout .= "}\n";
        break;
    case MATCH:
        $expout .= "::".$this->repchar($question->name)."::".$tfname.$this->repchar( $question->questiontext, $textformat )."{\n";
        foreach($question->options->subquestions as $subquestion) {
            $expout .= "\t=".$this->repchar( $subquestion->questiontext )." -> ".$this->repchar( $subquestion->answertext )."\n";
        }
        $expout .= "}\n";
        break;
    case DESCRIPTION:
        $expout .= "// DESCRIPTION type is not supported\n";
        break;
    case MULTIANSWER:
        $expout .= "// CLOZE type is not supported\n";
        break;
    default:
        // check for plugins
        if ($out = $this->try_exporting_using_qtypes( $question->qtype, $question )) {
            $expout .= $out;
        }
        else {
            notify("No handler for qtype '$question->qtype' for GIFT export" );
        }
    }
    // add empty line to delimit questions
    $expout .= "\n";
    return $expout;
}
}
?>
