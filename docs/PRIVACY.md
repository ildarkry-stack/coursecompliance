# Privacy and stored data

CourseCompliance is an administrative quality-control plugin. It does not store student submissions, Quiz attempts or student answers in its own tables.

## Personal data stored by CourseCompliance

The plugin stores limited administrator-related operational metadata in the Moodle database:

- the user id of the administrator who started an audit;
- the user id of the administrator who created a bulk activity-operation batch;
- the user id and administrative reason recorded for a compliance exception;
- timestamps and operational status associated with those actions.

Audit findings, question diagnostics and activity-change rows are linked to the corresponding audit or operation. They describe course configuration and assessment structure rather than student responses.

## Moodle Messaging

When an audit completes or fails, CourseCompliance can send a notification to the administrator who started the audit through Moodle's core Messaging subsystem. Message storage, delivery preferences and message deletion are handled by Moodle core. CourseCompliance declares this subsystem use through the Privacy API.

## Privacy API coverage

`tool_coursecompliance\privacy\provider` implements:

- metadata declaration;
- context discovery for one user;
- user discovery in the system context (`core_userlist_provider`);
- export of stored CourseCompliance administrator metadata;
- deletion for one approved user;
- deletion for an approved list of users;
- deletion of all CourseCompliance personal data in the system context.

CourseCompliance stores its administrator-related personal data in the system context because the audited courses and bulk operations can span many course categories.

## External services

CourseCompliance does not send course, user or assessment data to an external CourseCompliance service and does not require external API credentials.
