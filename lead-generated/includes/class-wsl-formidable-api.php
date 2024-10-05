<?php

class Wsl_formidable_Api extends Lg_Crm{
        
    public $cf7;
    public $submit_result;
    public $submission;
    
    public $form_fields;
    public $form_values;
    public $additional_data;
    public $meta = array();
    
    public $mapped_data;
    
    public function __construct($form_fields, $formData) {
        $f_fields = $this->get_field_data($form_fields, $formData);
        $this->form_fields = $f_fields;
    }
    public function call(){      
        $this->set_raw_additional_data();  
        $this->set_meta();
        $this->map_fields_and_data();
        $result = $this->send_call();
        $this->process_result($result);
    }
    
    public function set_raw_additional_data(){
        $data = array();
        foreach($this->form_fields as $field){
            $field['name'] = strtolower($field['name']);
            $data[$field['name']] = $field;
        }
        $this->additional_data = $data;
    }

    public function map_fields_and_data(){
        $data = array();
        $data['email'] = $this->find_field('email',array('email','mail','your-email'));
        $data['phone'] = $this->find_field('phone',array('phone'));
        $data['name'] = $this->map_name();
        $data['address'] = $this->map_address();
        $data['source'] = $this->get_source_data();
        $data['additional_data'] = $this->process_additional_data();
        $data['meta'] = $this->meta;
        
        $this->mapped_data = $data;
    }

    public function process_additional_data(){
        $data = array();
        foreach($this->form_fields as $field){
            $field['name'] = strtolower($field['name']);
            if($field['type'] != 'hidden'){
                if(isset($this->additional_data[$field['name']])){
                    $data[$field['name']] = array(
                        'value' => $field['value'],
                        'name' => $field['name'],
                        'basetype' => $field['type'],
                        'raw_values' => $field['value'],
                        'label' => $this->generate_label_by_name($field['name']),
                        'show' => $field['type'] == 'hidden' ? 'hide' : 'show'
                    );
                }
            }
        }
        return $data;
    }

    public function find_field($type,$names = array(),$unset_additional = true){
        $value = '';
        foreach($this->form_fields as $key=>$field){
            if($field['type'] == $type){
                $value = $field['value'];
                if($unset_additional){
                    $this->unset_additional_data($field['name']);
                }
                break;
            }
        }
        if($value == '' && is_array($names) && count($names) > 0){
            $value = $this->find_field_by_name($names,$unset_additional);
        }
        return $value;
    }

    public function find_field_by_name($names,$unset_additional = true){
        $value = '';
        foreach($this->form_fields as $field){
            if(in_array(strtolower($field['name']),$names)){
                $value = $field['value'];
                if($unset_additional){
                    $this->unset_additional_data($field['name']);
                }
                break;
            }
        }
        return $value;
    }
    
    public function get_source_data(){
        $company_id = lgcrm_get_setting('send_to_company');
        return array(
                   'id' => $company_id,
                   'website' =>  site_url(),
                   'name' => get_bloginfo('name')
                );
    }
    
    public function set_submission_instance($submission, $formData){
        
        global $wpdb;
        $fields_for_sub = array();
        $form_settings = array();
        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}frm_item_metas as metas, {$wpdb->prefix}frm_fields as field WHERE item_id = '$submission' AND field.id = metas.field_id");
        $cnt_id = 1;
        $address = "";
        foreach($data as $key=>$val){
            if($val->type == "address"){
                $ser_address = unserialize( $val->meta_value );
                
                foreach($ser_address as $ak=>$av){
                    $fields_for_sub[] = array(
                        "name"      => strtolower($ak),
                        "value"     => $av,
                        "id"        => $cnt_id++,
                        "type"      => $ak,
                        "label"     => ucfirst($ak)
                    );

                    $address .= $av.' ';
                }

                $fields_for_sub[] = array(
                    "name"      => strtolower($val->name),
                    "value"     => $address,
                    "id"        => $val->id,
                    "type"      => $val->type,
                    "label"     => $val->name
                );
            }else{
                $fields_for_sub[] = array(
                    "name"      => strtolower($val->name),
                    "value"     => $val->meta_value,
                    "id"        => $val->id,
                    "type"      => $val->type,
                    "label"     => $val->name
                );
            }
        }

        $form_settings["id"] = $formData;
        $form_settings["field_id"] = $formData;
        $form_settings["fields"] = $fields_for_sub;

        $this->submission = $form_settings;
        return $this;
    }

    public function set_meta(){
        global $wp;
        $this->meta = array(
            'field_id' => $this->submission['field_id'],
            'post_url' => home_url( $wp->request ),
            'contact_form_id' => isset($this->submission['form_id']) ? $this->submission['form_id'] : "",
            'remote_ip' => $_SERVER['REMOTE_ADDR'],
            'wp_user_id' => '',
            'plugin_name' => "formaidable",
        );
    }
    
    public function send_call(){
        $url = $this->api_url;
        
        $wsl_api = new Wsl_Api();
        $response = $wsl_api->call($url,$this->mapped_data);
        return $response;
    }
    
    public function map_name(){
        $value = '';
        foreach($this->form_fields as $field){
            if($field['type'] == 'name'){
                $value = $field['value'];
                $this->unset_additional_data($field['name']);
                break;
            }
            if(strpos(strtolower($field['name']), 'name') !== false){
                $value = $field['value'];
                $this->unset_additional_data($field['name']);
                break;
            }
        }
        return $value;
    }
    
    public function map_address(){
        $value = array();
        foreach($this->form_fields as $key => $field){
            if($field['type'] == 'address'){
                $value['address'] = $field['value'];
                $this->unset_additional_data($field['name']);
                break;
            }
            if(strpos(strtolower($field['name']), 'address') !== false){
                $value['address'] = $field['value'];
                $this->unset_additional_data($field['name']);
                break;
            }
        }

        $city = $this->find_field('city',array('city'));
        if($city){
            $value['city'] = $city;
            $this->unset_additional_data($key);
        }
        $state = $this->find_field('state',array('state'));
        if($state){
            $value['state'] = $state;
            $this->unset_additional_data($key);
        }
        $zip = $this->find_field('zipcode',array('zip','zipcode'));
        if($zip){
            $value['zipcode'] = $zip;
            $this->unset_additional_data($key); 
        }
        return $value;
    }
    
    public function unset_additional_data($name){
        $name = strtolower($name);
        unset($this->additional_data[$name]);
        return $this;
    }
    
    public function generate_label($field){
        $label = '';
        if(count($field->options) < 1){
            return $this->generate_label_by_name($field->name);
        }
        
        foreach($field->options as $option){
            $break_option = explode(':', $option);
            if(count($break_option) == 2 && $break_option[0] == 'lead-label'){
                $label = str_replace('_', ' ', $break_option[1]);
                break;
            }
        }
        
        if($label == ''){
            $label = $this->generate_label_by_name($field->name);
        }
        
        return $label;
    }
    
    public function generate_label_by_name($name){
        return str_replace(array('_','-'), ' ', $name);
    }
    
    public function process_result($result){
        $data = $result;
        $send_to_company = lgcrm_get_setting('send_to_company');
        if($send_to_company && $send_to_company != 0){
            //All good
        }else{
            if(isset($data->source_id)){
                lgcrm_update_setting('send_to_company',$data->source_id);
            }
        }
        if(isset($data->source_id)){
            update_option('wsl_source_id', $data->source_id, false);
        }
    }

    public function get_field_data($form_fields, $formData){
        global $wpdb;
        $fields = array();
        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}frm_item_metas as metas, {$wpdb->prefix}frm_fields as field WHERE item_id = '$form_fields' AND field.id = metas.field_id");
        $cnt_id = 1;
        $address = "";
        
        foreach($data as $key=>$val){
            if($val->type == "address"){
                $ser_address = unserialize( $val->meta_value );
                
                foreach($ser_address as $ak=>$av){
                    $fields[] = array(
                        "name"      => strtolower($ak),
                        "value"     => $av,
                        "id"        => $cnt_id++,
                        "type"      => $ak,
                        "label"     => ucfirst($ak)
                    );

                    $address .= $av.' ';
                }

                $fields[] = array(
                    "name"      => strtolower($val->name),
                    "value"     => $address,
                    "id"        => $val->id,
                    "type"      => $val->type,
                    "label"     => $val->name
                );
            }else{
                $fields[] = array(
                    "name"      => strtolower($val->name),
                    "value"     => $val->meta_value,
                    "id"        => $val->id,
                    "type"      => $val->type,
                    "label"     => $val->name
                );
            }
        }
        return $fields;
    }
}