<?php
class SAPWC_UDFields_Helper {
    public static function get_udf_fields_from_metadata($xml_string) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        $udf_fields = [];

        if (!$xml) return $udf_fields;

        foreach ($xml->children('edmx', true)->DataServices->children() as $schema) {
            foreach ($schema->EntityType as $entity) {
                if ((string)$entity['Name'] === 'Document') {
                    foreach ($entity->Property as $prop) {
                        $name = (string)$prop['Name'];
                        if (str_starts_with($name, 'U_')) {
                            $udf_fields[] = $name;
                        }
                    }
                }
            }
        }
        return $udf_fields;
    }
}
