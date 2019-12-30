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

Onderzoek:

overeenkomsten tussen 'dezelfde les' in basisrooster
 571199, 573105, 575049, 577275, 579462, 581578, 583786, 585912, 588145, 590275, 592023

start/end time, bos_id, type, visible stuff, booleans, 

overeenkomsten tussen 'dezelfde les' in basisrooster met wijzigingen

409971, 411997, 439236, 417604, 553096, 555308, 557464, 559598, 529745, 531576, 532420, 535535,538005, 539930,541457, 544240

basisrooster?

een rooster is een basisrooster alles alle unhidden lessen in dat rooster
- valid zijn
- niet cancelled zijn
- niet new zijn
!?!?!?

SELECT appointment_zid, appointment_instance_zid, appointment_created, appointment_lastModified, appointment_appointmentLastModified FROM `appointments` ORDER BY appointment_zid
:wq

Brian eigen: https://imgflip.com/i/3krbxe https://i.imgflip.com/3krbxe.jpg
Brian niet: https://imgflip.com/i/3krczb https://i.imgflip.com/3krczb.jpg

