<?php
class GFSalesforceData{

    public static function update_table(){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();

        if ( ! empty($wpdb->charset) )
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
            $charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE $table_name (
              id mediumint(8) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              is_active tinyint(1) not null default 1,
              sort tinyint(1) null default 0,
              meta longtext,
              PRIMARY KEY  (id),
              KEY form_id (form_id)
            )$charset_collate;";

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_salesforce_table_name(){
        global $wpdb;
        return $wpdb->prefix . "rg_salesforce";
    }

    public static function get_feeds(){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();
        $form_table_name = RGFormsModel::get_form_table_name();
        $sql = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
                FROM $table_name s
                INNER JOIN $form_table_name f ON s.form_id = f.id
                ORDER BY s.sort";

        $results = $wpdb->get_results($sql, ARRAY_A);

        $count = sizeof($results);
        for($i=0; $i<$count; $i++){
            $results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
        }

        return $results;
    }

    public static function delete_feed($id){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();
        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id=%s", $id));
    }

    public static function get_feed_by_form($form_id, $only_active = false){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();
        $active_clause = $only_active ? " AND is_active=1" : "";
        $sql = $wpdb->prepare("SELECT id, form_id, is_active, meta FROM $table_name WHERE form_id=%d $active_clause ORDER BY sort", $form_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        if(empty($results))
            return array();

        //Deserializing meta
        $count = sizeof($results);
        for($i=0; $i<$count; $i++){
            $results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
        }
        return $results;
    }

    public static function get_feed($id){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();
        $sql = $wpdb->prepare("SELECT id, form_id, is_active, meta FROM $table_name WHERE id=%d ORDER BY sort", $id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        if(empty($results))
            return array();

        $result = $results[0];
        $result["meta"] = maybe_unserialize($result["meta"]);
        return $result;
    }

    public static function update_feed($id, $form_id, $is_active, $setting){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();
        $setting = maybe_serialize($setting);
        if($id == 0){
            $sql = "SELECT sort+1 as sort FROM {$table_name} ORDER BY sort DESC LIMIT 1";
            $results = $wpdb->get_row($sql, OBJECT);

            // insert
            $wpdb->insert($table_name, array('form_id' => $form_id,
                                                'is_active'=> $is_active,
                                                'sort' => ( isset( $results->sort ) ? $results->sort : 0 ),
                                                'meta' => $setting),
                                        array('%d', '%d', '%s', '%s'));
            $id = $wpdb->get_var('SELECT LAST_INSERT_ID()');
        }
        else{
            // update
            $wpdb->update($table_name, array('form_id' => $form_id, 'is_active'=> $is_active, 'meta' => $setting), array('id' => $id), array('%d', '%d', '%s'), array('%d'));
        }

        return $id;
    }

    /**
     * @since  3.1
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function update_feed_order($data){
        global $wpdb;
        $table_name = self::get_salesforce_table_name();

        if(!empty($data)) {
            foreach($data as $order=>$id) {
                $wpdb->update($table_name,
                    array('sort' => $order),
                    array('id' => $id),
                    array('%d'),
                    array('%d')
                );
            }
        }

        return true;
    }

    public static function drop_tables(){
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_salesforce_table_name());
    }
}
?>
