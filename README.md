- does not support multiple branches per project

- at this moment, only 'lessons' are displayed

- multiple search terms (separated by comma) are not supported

- in student view: no list of groups is displayed

- in group view, no list of students is displayed

- weekrooster not implemented

- als ln is 'Afgemeld', dan wordt het rooster niet goed weergegeven (afgemelde lessen staan niet in beeld, ook niet in het basisrooster)


VALUES ( ?, ? )',
                        $entity_id, $groups_egrp_id);
VALUES ( ?, ? )',
                        $entity_id, $groups_egrp_id);
VALUES ( ?, ? )',
                        $entity_id, $groups_egrp_id);

SELECT * FROM agstds LEFT JOIN ( SELECT egrp_id groups_egrp_id, egrp groups FROM egrps ) AS groups USING (groups_egrp_id) LEFT JOIN ( SELECT egrp_id subjects_egrp_id, egrp subjects FROM egrps ) AS subjects USING (subjects_egrp_id) LEFT JOIN ( SELECT egrp_id teachers_egrp_id, egrp teachers FROM egrps ) AS teachers USING (teachers_egrp_id) LEFT JOIN ( SELECT egrp_id locations_egrp_id, egrp locations FROM egrps ) AS locations USING (locations_egrp_id)
