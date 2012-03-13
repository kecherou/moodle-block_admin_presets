<?php


/**
 * Main admin_preset_setting class
 *
 * Provides basic methods like set_value and
 * set_visiblevalue to extend in most cases
 *
 * @abstract
 * @since      Moodle 2.0
 * @package    block/admin_presets
 * @copyright  2010 David Monllaó <david.monllao@urv.cat>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt
 */
abstract class admin_preset_setting {


    /**
     * @var admin_setting
     */
    protected $settingdata;

    /**
     * @var admin_presets_delegation
     */
    protected $delegation;


    /**
     * The setting DB value
     *
     * @var mixed
     */
    protected $value;


    /**
     * Stores the visible value of the setting DB value
     *
     * @var string
     */
    protected $visiblevalue;


    /**
     * Text to display on the TreeView
     *
     * @var string
     */
    protected $text;


    /**
     * For multiple value settings, used to look for the other values
     *
     * @var string
     */
    protected $attributes = false;


    /**
     * To store the setting attributes
     *
     * @var array
     */
    protected $attributesvalues;


    /**
     * Stores the setting data and the selected value
     *
     * @param       object         $settingdata         admin_setting subclass
     * @param       mixed          $dbsettingvalue      Actual value
     */
    public function __construct(admin_setting $settingdata, $dbsettingvalue) {

        $this->settingdata = $settingdata;
        $this->delegation = new admin_presets_delegation();

        if ($this->settingdata->plugin == '') {
            $this->settingdata->plugin = 'none';
        }

        // Cleaning value
        $this->set_value($dbsettingvalue);
    }


    /**
     * Returns the TreeView node identifier
     */
    public function get_id() {
        return $this->settingdata->name.'@@'.$this->settingdata->plugin;
    }

    public function get_value() {
        return $this->value;
    }

    public function get_visiblevalue() {
        return $this->visiblevalue;
    }

    public function get_description() {

        // PARAM_TEXT clean because the alt attribute does not support html
        $description = clean_param($this->settingdata->description, PARAM_TEXT);
        return $this->encode_string($description);
    }

    public function get_text() {
        return $this->encode_string($this->text);
    }

    public function get_attributes() {
        return $this->attributes;
    }

    public function get_attributes_values() {
        return $this->attributesvalues;
    }

    public function get_settingdata() {
        return $this->settingdata;
    }

    /**
     * Sets the text to display on the settings tree
     *
     * Default format: I'm a setting visible name (setting value: "VALUE")
     */
    public function set_text() {

        $this->set_visiblevalue();

        $namediv = '<div class="admin_presets_tree_name">'.$this->settingdata->visiblename.'</div>';
        $valuediv = '<div class="admin_presets_tree_value">'.$this->visiblevalue.'</div>';

        $this->text = $namediv.$valuediv.'<br/>';
    }


    /**
     * Encodes a string to send it to js
     *
     * @param string $string
     */
    protected function encode_string($string) {

        $encoded = rawurlencode($string);
        return $encoded;
    }

    /**
     * Sets the setting value cleaning it
     *
     * Child classes should overwrite method to clean more acurately
     *
     * @param    mixed     $value      Setting value
     * @return   mixed                 Returns false if wrong param value
     */
    protected function set_value($value) {
        $this->value = $value;
    }


    /**
     * Sets the visible name for the setting selected value
     *
     * In most cases the child classes will overwrite
     */
    protected function set_visiblevalue() {
        $this->visiblevalue = $this->value;
    }


    public function set_attribute_value($name, $value) {
        $this->attributesvalues[$name] = $value;
    }


    /**
     * Stores the setting into database, logs the change and returns the config_log inserted id
     *
     * Copy of config_write method of the admin_setting class
     *
     * @param      string      $name
     * @param      mixed       $value
     * @return     integer                     config_log inserted id
     */
    public function save_value($name = false, $value = NULL) {

        global $USER, $DB;

        // Object values if no arguments
        if ($value === NULL) {
            $value = $this->value;
        }
        if (!$name) {
            $name = $this->settingdata->name;
        }

        // Plugin name or NULL
        $plugin = $this->settingdata->plugin;
        if ($plugin == 'none' || $plugin == '') {
            $plugin = NULL;
        }

        // Getting the actual value
        $actualvalue = get_config($plugin, $name);


        // If it's the same it's not necessary
        if ($actualvalue == $value) {
            return false;
        }

        set_config($name, $value, $plugin);

        // Log the change (pasted from admin_setting class)
        $log = new object();
        $log->userid       = during_initial_install() ? 0 :$USER->id; // 0 as user id during install
        $log->timemodified = time();
        $log->plugin       = $plugin;
        $log->name         = $name;
        $log->value        = $value;
        $log->oldvalue     = $actualvalue;

        // Getting the inserted config_log id
        if (!$id = $DB->insert_record('config_log', $log)) {
            print_error('errorinserting', 'block_admin_presets');
        }

        return $id;
    }


    /**
     * Saves the setting attributes values
     *
     * @return     array        Array of inserted ids (in config_log)
     */
    public function save_attributes_values() {

        // Plugin name or NULL
        $plugin = $this->settingdata->plugin;
        if ($plugin == 'none' || $plugin == '') {
            $plugin = NULL;
        }

        if (!$this->attributesvalues) {
            return false;
        }

        // To store inserted ids
        $ids = array();
        foreach ($this->attributesvalues as $name => $value) {

            // Getting actual setting
            $actualsetting = get_config($plugin, $name);

            // If it's the actual setting get off
            if ($value == $actualsetting) {
                return false;
            }

            if ($id = $this->save_value($name, $value)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

}


/**
 * Simulates multiple herence
 *
 * Methods used by classes C which extends from A but also needs methods from B.
 * Classes C and also B delegates to that methods.
 */
class admin_presets_delegation {

    /**
     * Adds a piece of string to the $type setting
     *
     * @param     boolean    $value
     * @param     string     $type    Indicates the "extra" setting
     * @return    string
     */
    public function extra_set_visiblevalue($value, $type) {

        // Adding the advanced value to the text string if present
        if ($value) {
            $string = get_string('markedas'.$type, 'block_admin_presets');
        } else {
            $string = get_string('markedasnon'.$type, 'block_admin_presets');
        }

        // Adding the advanced state
        return ', '.$string;
    }

}

///////////////////////// TEXT /////////////////////////


/**
 * Basic text setting, cleans the param using the admin_setting paramtext attribute
 */
class admin_preset_setting_configtext extends admin_preset_setting {

    /**
     * Validates the value using paramtype attribute
     *
     * @param    string    $value
     * @return   boolean              Cleaned or not, but always true
     */
    protected function set_value($value) {

        $this->value = $value;

        if (empty($this->settingdata->paramtype)) {

            // For configfile, configpasswordunmask...
            $this->settingdata->paramtype = 'RAW';
        }

        $paramtype = 'PARAM_'.strtoupper($this->settingdata->paramtype);


        // Regexp
        if (!defined($paramtype)) {
            $this->value = preg_replace($this->settingdata->paramtype, '', $this->value);

        // Standard moodle param type
        } else {
            $this->value = clean_param($this->value, constant($paramtype));
        }

        return true;
    }
}

class admin_preset_setting_configtextarea extends admin_preset_setting_configtext {}

class admin_preset_setting_configfile extends admin_preset_setting_configtext {}

class admin_preset_setting_configexecutable extends admin_preset_setting_configfile {}

class admin_preset_setting_configdirectory extends admin_preset_setting_configfile {}

class admin_preset_setting_configpasswordunmask extends admin_preset_setting_configtext {}

class admin_preset_setting_langlist extends admin_preset_setting_configtext {}

class admin_preset_setting_configcolourpicker extends admin_preset_setting_configtext {}

class admin_preset_setting_emoticons extends admin_preset_setting {}

class admin_preset_setting_confightmleditor extends admin_preset_setting_configtext {}

/**
 * Adds the advanced attribute
 */
class admin_preset_setting_configtext_with_advanced extends admin_preset_setting_configtext {


    public function __construct(admin_setting $settingdata, $dbsettingvalue) {

        // To look for other values
        $this->attributes = array('fix' => $settingdata->name.'_adv');
        parent::__construct($settingdata, $dbsettingvalue);
    }


    /**
     * Delegates
     */
    protected function set_visiblevalue() {
        parent::set_visiblevalue();
        $this->visiblevalue .= $this->delegation->extra_set_visiblevalue($this->attributesvalues[$this->attributes['fix']], 'advanced');
    }
}


class admin_preset_setting_configiplist extends admin_preset_setting_configtext {

    protected function set_value($value) {

        // Just in wrong format case
        $this->value = '';

        // Check ip format
        if ($this->settingdata->validate($value) !== true) {
            $this->value = false;
            return false;
        }

        $this->value = $value;
        return true;
    }
}


/**
 * Reimplementation to allow human friendly view of the selected regexps
 */
class admin_preset_setting_devicedetectregex extends admin_preset_setting_configtext {

    public function set_visiblevalue() {

        $values = json_decode($this->get_value());

        $this->visiblevalue = '';
        foreach ($values as $key => $value) {
            $this->visiblevalue .= $key . ' = ' . $value . ', ';
        }
        $this->visiblevalue = rtrim($this->visiblevalue, ', ');
    }
}


/**
 * Reimplemented to store values in course table, not in config or config_plugins
 */
class admin_preset_setting_sitesettext extends admin_preset_setting_configtext {

    public function save_value() {

        global $USER, $DB;


        $sitecourse = $DB->get_record('course', array('id' => 1));
        $actualvalue = $sitecourse->{$this->settingdata->name};

        // If it's the same value skip
        if ($actualvalue == $this->value) {
            return false;
        }

        // Plugin name or ''
        $plugin = $this->settingdata->plugin;
        if ($plugin == 'none' || $plugin == '') {
            $plugin = NULL;
        }

        // Updating mdl_course
        $sitecourse->{$this->settingdata->name} = $this->value;
        $DB->update_record('course', $sitecourse);


        // Pasted from admin_setting class
        $log = new object();
        $log->userid       = during_initial_install() ? 0 :$USER->id; // 0 as user id during install
        $log->timemodified = time();
        $log->plugin       = $plugin;
        $log->name         = $this->settingdata->name;
        $log->value        = $this->value;
        $log->oldvalue     = $actualvalue;

        // Getting the inserted config_log id
        if (!$id = $DB->insert_record('config_log', $log)) {
            print_error('errorinserting', 'block_admin_presets');
        }

        return $id;

    }
}

class admin_preset_setting_special_frontpagedesc extends admin_preset_setting_sitesettext {}


///////////////////////// SELECTS /////////////////////////


class admin_preset_setting_configselect extends admin_preset_setting {


    /**
     * $value must be one of the setting choices
     *
     * @return     boolean          True if the value one of the setting choices
     */
    protected function set_value($value) {

        foreach ($this->settingdata->choices as $key => $choice) {

            if ($key == $value) {
                $this->value = $value;
                return true;
            }
        }

        $this->value = false;
        return false;
    }


    protected function set_visiblevalue() {

        // Just to avoid heritage problems
        if (empty($this->settingdata->choices[$this->value])) {
            $this->visiblevalue = '';
        } else {
            $this->visiblevalue = $this->settingdata->choices[$this->value];
        }

    }
}

class admin_preset_setting_bloglevel extends admin_preset_setting_configselect{

    /**
     * Extended to change the block visibility
     */
    public function save_value($name = false, $value = false) {

        if (!$id = parent::save_value($name, $value)) {
            return false;
        }

        // Pasted from admin_setting_bloglevel (can't use write_config)
        if ($value == 0) {
            $DB->set_field('block', 'visible', 0, array('name' => 'blog_menu'));
        } else {
            $DB->set_field('block', 'visible', 1, array('name' => 'blog_menu'));
        }

        return $id;
    }
}

class admin_preset_setting_special_selectsetup extends admin_preset_setting_configselect{}

class admin_preset_setting_sitesetselect extends admin_preset_setting_configselect {}

/**
 * Adds support for the "advanced" attribute
 */
class admin_preset_setting_configselect_with_advanced extends admin_preset_setting_configselect {

    protected $advancedkey;

    public function __construct(admin_setting $settingdata, $dbsettingvalue) {

        // Getting the advanced defaultsetting attribute name
        foreach ($settingdata->defaultsetting as $key => $defaultvalue) {
            if ($key != 'value') {
                $this->advancedkey = $key;
            }
        }


        // To look for other values
        $this->attributes = array($this->advancedkey => $settingdata->name.'_adv');
        parent::__construct($settingdata, $dbsettingvalue);
    }


    /**
     * Funcionality used by other _with_advanced settings
     */
    protected function set_visiblevalue() {
        parent::set_visiblevalue();
        $this->visiblevalue .= $this->delegation->extra_set_visiblevalue($this->attributesvalues[$this->attributes[$this->advancedkey]], 'advanced');
    }
}


/**
 * A select with force and advanced options
 */
class admin_preset_setting_gradecat_combo extends admin_preset_setting_configselect {

    /**
     * One db value for two setting attributes
     *
     * @param unknown_type $settingdata
     * @param unknown_type $dbsettingvalue
     */
    public function __construct(admin_setting $settingdata, $dbsettingvalue) {

        // set_attribute_value() will mod the VARNAME_flag value
        $this->attributes = array('forced' => $settingdata->name.'_flag',
                                  'adv' => $settingdata->name.'_flag');
        parent::__construct($settingdata, $dbsettingvalue);
    }


    /**
     * Special treatment! the value be extracted from the $value argument
     */
    protected function set_visiblevalue() {
        parent::set_visiblevalue();

        $flagvalue = $this->attributesvalues[$this->settingdata->name.'_flag'];

        if (isset($flagvalue)) {

            if (($flagvalue % 2) == 1 ) {
                $forcedvalue = '1';
            } else {
                $forcedvalue = '0';
            }

            if ($flagvalue >= 2) {
                $advancedvalue = '1';
            } else {
                $advancedvalue = '0';
            }
            $this->visiblevalue .= $this->delegation->extra_set_visiblevalue($forcedvalue, 'forced');
            $this->visiblevalue .= $this->delegation->extra_set_visiblevalue($advancedvalue, 'advanced');
        }
    }
}


/**
 * Extends the base class and lists the selected values separated by comma
 */
class admin_preset_setting_configmultiselect extends admin_preset_setting {

    /**
     * Ensure that the $value values are setting choices
     */
    protected function set_value($value) {

        if ($value) {
            $options = explode(',', $value);
            foreach ($options as $key => $option) {

                foreach ($this->settingdata->choices as $key => $choice) {

                    if ($key == $value) {
                        $this->value = $value;
                        return true;
                    }
                }
            }

            $value = implode(',', $options);
        }

        $this->value = $value;
    }

    protected function set_visiblevalue() {

        $values = explode(',', $this->value);
        $visiblevalues = array();

        foreach ($values as $value) {

            if (!empty($this->settingdata->choices[$value])) {
                $visiblevalues[] = $this->settingdata->choices[$value];
            }
        }

        if (empty($visiblevalues)) {
            $this->visiblevalue = '';
            return false;
        }

        $this->visiblevalue = implode(', ', $visiblevalues);
    }

}


/**
 * Extends configselect to reuse set_valuevisible
 */
class admin_preset_setting_users_with_capability extends admin_preset_setting_configmultiselect {

    protected function set_value($value) {

        $this->settingdata->load_choices();

        // Dirty hack (the value stored in the DB is '')
        $this->settingdata->choices[''] = $this->settingdata->choices['$@NONE@$'];

        return parent::set_value($value);
    }

}


/**
 * A standard config multiselect with previous load_choices()
 */
abstract class admin_preset_setting_configmultiselect_with_loader extends admin_preset_setting_configmultiselect {

    protected function set_value($value) {

        $this->settingdata->load_choices();
        return parent::set_value($value);
    }
}

class admin_preset_setting_courselist_frontpage extends admin_preset_setting_configmultiselect_with_loader {}

class admin_preset_setting_configmultiselect_modules extends admin_preset_setting_configmultiselect_with_loader {}

/**
 * A standard config select with a previous load_choices()
 */
abstract class admin_presets_setting_configselect_with_loader extends admin_preset_setting_configselect {

    protected function set_value($value) {
        $this->settingdata->load_choices();
        return parent::set_value($value);
    }
}

class admin_preset_settings_country_select extends admin_presets_setting_configselect_with_loader {}

class admin_preset_setting_special_registerauth extends admin_presets_setting_configselect_with_loader {}

class admin_preset_setting_special_debug extends admin_presets_setting_configselect_with_loader {}

class admin_preset_settings_coursecat_select extends admin_presets_setting_configselect_with_loader {}

class admin_preset_setting_grade_profilereport extends admin_presets_setting_configselect_with_loader {}

class admin_preset_settings_num_course_sections extends admin_presets_setting_configselect_with_loader {}

class admin_preset_setting_question_behaviour extends admin_presets_setting_configselect_with_loader {}

class admin_preset_setting_configtime extends admin_preset_setting {

    protected function set_value($value) {

        $this->attributes = array('m' => $this->settingdata->name2);

        for ($i = 0 ; $i < 24 ; $i++) {
            $hours[$i] = $i;
        }

        if (empty($hours[$value])) {
            $this->value = false;
        }

        $this->value = $value;
    }

    protected function set_visiblevalue() {
        $this->visiblevalue = $this->value.':'.$this->attributesvalues[$this->settingdata->name2];
    }

    /**
     * To check that the value is one of the options
     *
     * @param  string  $name
     * @param  mixed  $value
     */
    public function set_attribute_value($name, $value) {

        for ($i = 0 ; $i < 60 ; $i = $i + 5) {
            $minutes[$i] = $i;
        }

        if (!empty($minutes[$value])) {
            $this->attributesvalues[$name] = $value;
        } else {
            $this->attributesvalues[$name] = $this->settingdata->defaultsetting['m'];
        }
    }
}


/////////////////////////// CHECKBOXES /////////////////////////


class admin_preset_setting_configcheckbox extends admin_preset_setting {


    protected function set_value($value) {
        $this->value = clean_param($value, PARAM_BOOL);
        return true;
    }


    protected function set_visiblevalue() {

        if ($this->value) {
            $str = get_string('yes');
        } else {
            $str = get_string('no');
        }

        $this->visiblevalue = $str;
    }
}


class admin_preset_setting_configcheckbox_with_advanced extends admin_preset_setting_configcheckbox {


    public function __construct(admin_setting $settingdata, $dbsettingvalue) {

        // To look for other values
        $this->attributes = array('adv' => $settingdata->name.'_adv');
        parent::__construct($settingdata, $dbsettingvalue);
    }


    /**
     * Uses delegation
     */
    protected function set_visiblevalue() {
        parent::set_visiblevalue();
        $this->visiblevalue .= $this->delegation->extra_set_visiblevalue($this->attributesvalues[$this->attributes['adv']], 'advanced');
    }

}


class admin_preset_setting_configcheckbox_with_lock extends admin_preset_setting_configcheckbox {

    public function __construct(admin_setting $settingdata, $dbsettingvalue) {

        // To look for other values
        $this->attributes = array('locked' => $settingdata->name.'_locked');
        parent::__construct($settingdata, $dbsettingvalue);
    }


    /**
     * Uses delegation
     */
    protected function set_visiblevalue() {
        parent::set_visiblevalue();
        $this->visiblevalue .= $this->delegation->extra_set_visiblevalue($this->attributesvalues[$this->attributes['locked']], 'locked');
    }

}


class admin_preset_setting_sitesetcheckbox extends admin_preset_setting_configcheckbox {}

class admin_preset_setting_special_adminseesall extends admin_preset_setting_configcheckbox {}

class admin_preset_setting_regradingcheckbox extends admin_preset_setting_configcheckbox {}

class admin_preset_setting_special_gradelimiting extends admin_preset_setting_configcheckbox {}


/**
 * Abstract class to be extended by multicheckbox settings
 *
 * Now it's a useless class, child classes could extend admin_preset_setting_configmultiselect_with_loader
 *
 * @abstract
 */
class admin_preset_setting_configmulticheckbox extends admin_preset_setting_configmultiselect_with_loader {}

class admin_preset_setting_pickroles extends admin_preset_setting_configmulticheckbox {}

class admin_preset_setting_special_coursemanager extends admin_preset_setting_configmulticheckbox {}

class admin_preset_setting_special_coursecontact extends admin_preset_setting_configmulticheckbox {}

class admin_preset_setting_special_gradebookroles extends admin_preset_setting_configmulticheckbox {}

class admin_preset_setting_special_gradeexport extends admin_preset_setting_configmulticheckbox {}

class admin_preset_setting_special_backupdays extends admin_preset_setting {


    protected function set_value($value) {
        $this->value = clean_param($value, PARAM_SEQUENCE);
    }

    protected function set_visiblevalue() {

        $days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        $this->settingdata->load_choices();

        $selecteddays = array();

        $week = str_split($this->value);
        foreach ($week as $key => $day) {
            if ($day) {
                $index = $days[$key];
                $selecteddays[] = $this->settingdata->choices[$index];
            }
        }

        $this->visiblevalue = implode(', ', $selecteddays);
    }
}


/////////////////////////////// OTHERS /////////////////////////////


class admin_preset_setting_special_calendar_weekend extends admin_preset_setting {

    protected function set_visiblevalue() {

        $days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        for ($i=0; $i<7; $i++) {
            if ($this->value & (1 << $i)) {
                $settings[] = get_string($days[$i], 'calendar');
            }
        }

        $this->visiblevalue = implode(', ', $settings);
    }
}


class admin_preset_setting_quiz_reviewoptions extends admin_preset_setting {

    // admin_setting_quiz_reviewoptions vars can't be accessed
    private static $times = array(
            QUIZ_REVIEW_IMMEDIATELY => 'reviewimmediately',
            QUIZ_REVIEW_OPEN => 'reviewopen',
            QUIZ_REVIEW_CLOSED => 'reviewclosed');

    private static $things = array(
            QUIZ_REVIEW_RESPONSES => 'responses',
            QUIZ_REVIEW_ANSWERS => 'answers',
            QUIZ_REVIEW_FEEDBACK => 'feedback',
            QUIZ_REVIEW_GENERALFEEDBACK => 'generalfeedback',
            QUIZ_REVIEW_SCORES => 'scores',
            QUIZ_REVIEW_OVERALLFEEDBACK => 'overallfeedback');

    /**
     * Stores the setting data and the selected value
     *
     * @param       object         $settingdata         admin_setting subclass
     * @param       mixed          $dbsettingvalue      Actual value
     */
    public function __construct(admin_setting $settingdata, $dbsettingvalue) {
        $this->attributes = array('fix' => $settingdata->name.'_adv');
        parent::__construct($settingdata, $dbsettingvalue);
    }


    /**
     * Delegates
     */
    protected function set_visiblevalue() {

        $marked = array();

        foreach (admin_preset_setting_quiz_reviewoptions::$times as $timemask => $time) {
            foreach (admin_preset_setting_quiz_reviewoptions::$things as $typemask => $type) {
                if ($this->value & $timemask & $typemask) {
                    $marked[$time][] = get_string($type, "quiz");
                }
            }
        }

        foreach ($marked as $time => $types) {
            $visiblevalues[] = '<strong>'.get_string($time, "quiz").':</strong> '.implode(', ', $types);
        }
        $this->visiblevalue = implode('<br/>', $visiblevalues);

        if ($this->attributesvalues[$this->attributes['fix']]) {
            $string = get_string("markedasnonadvanced", "block_admin_presets");
        } else {
            $string = get_string("markedasadvanced", "block_admin_presets");
        }

        $this->visiblevalue .= '<br/>'.ucfirst($string);
    }

}

//class admin_preset_setting_manageauths extends admin_preset_setting {}
//class admin_preset_setting_manageenrols extends admin_preset_setting {}
//class admin_preset_setting_manageeditors extends admin_preset_setting {}
//class admin_preset_setting_managelicenses extends admin_preset_setting {}
//class admin_preset_setting_manageportfolio extends admin_preset_setting {}
//class admin_preset_setting_managerepository extends admin_preset_setting {}
//class admin_preset_setting_webservicesoverview extends admin_preset_setting {}
//class admin_preset_setting_manageexternalservices extends admin_preset_setting {}
//class admin_preset_setting_managewebserviceprotocols extends admin_preset_setting {}
//class admin_preset_setting_managewebservicetokens extends admin_preset_setting {}
//class admin_preset_setting_manageplagiarism extends admin_preset_setting {}