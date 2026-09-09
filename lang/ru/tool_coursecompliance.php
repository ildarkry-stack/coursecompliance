<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Russian language strings for the plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesschecks'] = 'Доступ и безопасность браузера';
$string['activitiesfound'] = 'Найдено элементов: {$a}';
$string['activity'] = 'Элемент';
$string['activitymissing'] = 'Элемент курса больше не существует.';
$string['activityoperations'] = 'Операции с активностями';
$string['activityoperations_intro'] = 'Единая временная шкала и массовые операции для тестов, заданий и форумов ' .
    'сразу по категории ' .
    'курсов. Любое изменение проходит предпросмотр, проверку конфликтов, журналирование и ' .
    'применяется фоновой задачей.';
$string['activityoutsidescope'] = 'Один из выбранных элементов находится вне категории/курса, для которых построен этот список.';
$string['activitysearch'] = 'Поиск по элементу, курсу или idnumber';
$string['activitytype'] = 'Тип элемента';
$string['actual'] = 'Фактически';
$string['addprofile'] = 'Добавить профиль';
$string['adjustassignmentdates'] = 'Сохранять корректность дополнительных дат задания';
$string['adjustassignmentdates_help'] = 'Если включённая окончательная отсечка или срок выставления оценок ' .
    'окажется раньше нового ' .
    'срока сдачи, дата будет перенесена вперёд до нового срока сдачи. Отключённые дополнительные ' .
    'даты останутся отключёнными.';
$string['after'] = 'После';
$string['all'] = 'Все';
$string['allcourses'] = 'Все курсы категории и подкатегорий';
$string['allsections'] = 'Все разделы';
$string['applied'] = 'Применено';
$string['assessment'] = 'Контрольное мероприятие';
$string['assessmentauditor'] = 'Аудитор контрольных мероприятий';
$string['assessmentperiod'] = 'Период контроля';
$string['assessments'] = 'Контрольные мероприятия';
$string['assignidnumber'] = 'idnumber задания';
$string['assignstart'] = 'Начало приёма';
$string['backtoactivities'] = 'Вернуться к операциям с активностями';
$string['backtobatch'] = 'Вернуться к предпросмотру операции';
$string['backtosessionplanning'] = 'Вернуться к планированию сессии';
$string['batchhasnochanges'] = 'В этом пакете нет значений, которые требуется изменить. Если найдены проблемы целевых ' .
    'элементов, проверьте их список.';
$string['batchnotpreview'] = 'Эта операция уже не ожидает подтверждения.';
$string['batchnotqueued'] = 'Эта операция не находится в очереди на выполнение.';
$string['batchnotrollbackable'] = 'Эту операцию нельзя откатить.';
$string['batchowneronly'] = 'Применить предпросмотр может только пользователь, который его создал.';
$string['batchqueued'] = 'Операция поставлена в очередь. Moodle cron применит её в фоне.';
$string['batchrunning'] = 'Операция находится в очереди или выполняется. Moodle cron применяет изменения в фоне.';
$string['batchstatus_complete'] = 'Завершено';
$string['batchstatus_failed'] = 'Ошибка';
$string['batchstatus_partial'] = 'Завершено с пропусками/ошибками';
$string['batchstatus_preview'] = 'Предпросмотр';
$string['batchstatus_queued'] = 'В очереди';
$string['batchstatus_running'] = 'Выполняется';
$string['batchsummary_dates'] = 'Сохранение отредактированных дат элементов курса.';
$string['batchsummary_rename'] = 'Заменить «{$a->find}» на «{$a->replace}» в названиях выбранных элементов.';
$string['batchsummary_rollback'] = 'Откат операции #{$a->source}: можно безопасно вернуть {$a->eligible} изм.; {$a->conflicts} ' .
    'изм. уже были изменены после исходной операции и исключены.';
$string['batchsummary_session'] = 'Подготовлено расписание контрольных мероприятий для {$a} курсов.';
$string['batchsummary_shift'] = 'Сдвиг настроенных дат на {$a->amount} {$a->unit}(ед.).';
$string['batchsummary_visibility'] = 'Установить выбранным элементам состояние: {$a}.';
$string['before'] = 'До';
$string['bulkoperation'] = 'Массовая операция';
$string['category'] = 'Категория курсов';
$string['categoryrequired'] = 'Выберите категорию курсов.';
$string['changes'] = 'Изменений';
$string['changestatus_applied'] = 'Применено';
$string['changestatus_failed'] = 'Ошибка';
$string['changestatus_pending'] = 'Ожидает';
$string['changestatus_skipped'] = 'Пропущено';
$string['component'] = 'Область';
$string['configured'] = 'Настроено';
$string['confirmapply'] = 'Применить эту операцию';
$string['conflictchanged'] = 'Пропущено: элемент был изменён после создания предпросмотра.';
$string['copyofprofile'] = 'Копия: {$a}';
$string['coursechecks'] = 'Проверки курса';
$string['coursecompliance:manageprofiles'] = 'Управлять профилями соответствия и исключениями';
$string['coursecompliance:scheduleassessments'] = 'Планировать даты контрольных мероприятий по категориям курсов';
$string['courseonlyprofile'] = 'Только проверки курса';
$string['courses'] = 'Курсы';
$string['createexception'] = 'Создать исключение';
$string['critical'] = 'Критические';
$string['cutoffdate'] = 'Окончательная отсечка';
$string['dashboard'] = 'Контроль качества Moodle';
$string['datecutoff'] = 'Окончательная отсечка';
$string['dategradingdue'] = 'Срок оценивания';
$string['dateprimary'] = 'Открытие / начало';
$string['datesecondary'] = 'Срок / закрытие';
$string['datestate'] = 'Состояние дат';
$string['datestate_hasdates'] = 'Есть хотя бы одна дата';
$string['datestate_nodates'] = 'Даты не заданы';
$string['datestate_overdue'] = 'Основной срок/закрытие уже прошёл';
$string['datestate_upcoming'] = 'Откроется в будущем';
$string['defaultprofile'] = 'Экзаменационная дисциплина';
$string['defaultprofile_desc'] = 'Базовый профиль для дисциплин с экзаменом, определяемым по idnumber элемента курса exam.';
$string['disabled'] = 'Отключено';
$string['duedate'] = 'Срок сдачи';
$string['duplicateprofile'] = 'Дублировать';
$string['duplicateprofiletarget'] = 'idnumber «{$a}» уже используется другим периодом контроля в этом профиле.';
$string['duplicatesessiontargetconfig'] = 'idnumber «{$a}» назначен нескольким выбранным периодам для одного типа элемента.';
$string['editprofile'] = 'Редактирование профиля';
$string['emptyactivityname'] = 'Операция с названием сделает имя элемента «{$a}» пустым.';
$string['enabled'] = 'Включено';
$string['examidnumber'] = 'idnumber основного экзамена';
$string['exceptioncreated'] = 'Исключение создано. Оно будет учтено при следующем сканировании.';
$string['exceptiondeleted'] = 'Исключение удалено.';
$string['exceptionexpires'] = 'Действует до';
$string['exceptionreason'] = 'Причина';
$string['exceptions'] = 'Исключения из правил';
$string['exceptionsintro'] = 'Исключение подавляет конкретное правило для выбранного курса (и, если применимо, ' .
    'конкретного элемента). Исключения относятся к профилю и могут иметь срок действия.';
$string['expected'] = 'Ожидается';
$string['expectedattempts'] = 'Ожидаемое количество попыток';
$string['expectedattempts_help'] = 'Укажите 0, чтобы не проверять точное число попыток. В самом Quiz Moodle значение 0 означает ' .
    'неограниченные попытки, а в профиле Course Compliance 0 отключает данное правило.';
$string['expectedbrowsersecurity'] = 'Ожидаемое значение безопасности браузера';
$string['expectedbrowsersecurity_help'] = 'Оставьте пустым, чтобы не проверять. Можно указать значение ' .
    'browsersecurity Moodle, которое ' .
    'используется вашей настройкой безопасности браузера / Safe Exam Browser.';
$string['expectedquizgrade'] = 'Ожидаемая максимальная оценка теста';
$string['expectedtimelimit'] = 'Ожидаемое ограничение времени';
$string['failed'] = 'Ошибок';
$string['field'] = 'Поле';
$string['field_allowsubmissionsfromdate'] = 'Начало приёма работ';
$string['field_cutoffdate'] = 'Окончательная отсечка';
$string['field_duedate'] = 'Срок сдачи';
$string['field_gradingduedate'] = 'Срок выставления оценок задания';
$string['field_name'] = 'Название элемента';
$string['field_timeclose'] = 'Закрытие теста';
$string['field_timeopen'] = 'Открытие теста';
$string['field_visibilitystate'] = 'Видимость';
$string['filter'] = 'Применить фильтры';
$string['findtext'] = 'Найти текст';
$string['findtextrequired'] = 'Для нормализации названий укажите текст, который нужно найти.';
$string['gradingduedate'] = 'Срок выставления оценок';
$string['hideanswerswhileopen'] = 'Скрывать правильные ответы до закрытия теста';
$string['hidecorrectnesswhileopen'] = 'Скрывать правильность ответа до закрытия теста';
$string['ignored'] = 'Игнорировано по исключениям';
$string['includesubcategories'] = 'Включая подкатегории';
$string['intro'] = 'Аудит настроек курсов и готовности контрольных мероприятий. Массовые изменения вынесены в ' .
    'отдельный модуль «Операции с активностями» и всегда начинаются с предпросмотра.';
$string['invalidassigncutoff'] = 'У задания «{$a}» окончательная отсечка окажется раньше срока сдачи.';
$string['invalidassigndates'] = 'У задания «{$a}» срок сдачи окажется раньше начала приёма работ.';
$string['invalidassigngradingdue'] = 'У задания «{$a}» срок выставления оценок окажется раньше срока сдачи.';
$string['invaliddatetime'] = 'Одна из введённых дат имеет некорректный формат.';
$string['invalidforumcutoff'] = 'У форума «{$a}» окончательная отсечка окажется раньше срока сдачи.';
$string['invalidoperation'] = 'Выбрана неподдерживаемая операция.';
$string['invalidperioddates'] = 'Закрытие/срок сдачи должен быть позже открытия/начала.';
$string['invalidquestionrange'] = 'Максимум вопросов должен быть 0 либо не меньше минимального количества.';
$string['invalidquizdates'] = 'Тест «{$a}» будет закрываться раньше или в момент открытия.';
$string['invalidvisibilitystate'] = 'Некорректное состояние видимости.';
$string['legacyexamdatesdetected'] = 'Обнаружен установленный local_examdates. Course Compliance не изменяет и не переиспользует ' .
    'его старую историю; новые операции сессии выполняются через единый безопасный batch-движок ' .
    'Course Compliance.';
$string['locked'] = 'Заблокировано';
$string['maxoverrideattempts'] = 'Максимум попыток в override (0 = не проверять)';
$string['maxoverrideextension'] = 'Максимальное продление времени закрытия через override';
$string['maxquestions'] = 'Максимальное количество вопросов (0 = без ограничения)';
$string['message'] = 'Сообщение';
$string['mingradepass'] = 'Минимальный проходной балл';
$string['minquestions'] = 'Минимальное количество вопросов';
$string['missing'] = 'Отсутствует';
$string['noactivitiesfound'] = 'По выбранным фильтрам поддерживаемые элементы не найдены.';
$string['noactivitiesselected'] = 'Не выбрано ни одного поддерживаемого элемента.';
$string['nocompletedscans'] = 'Пока нет завершённых проверок соответствия.';
$string['nocoursesinscope'] = 'В выбранной категории не найдено курсов.';
$string['none'] = 'Нет';
$string['nothingtochange'] = 'Эта операция не изменит ни одного значения.';
$string['nothingtorollback'] = 'Нет изменений, которые можно безопасно откатить. Возможно, затронутые элементы уже были ' .
    'изменены после этой операции.';
$string['notices'] = 'Замечания';
$string['notselected'] = 'Не выбрано';
$string['openassessmentauditor'] = 'Открыть Assessment Auditor';
$string['openstandardsettings'] = 'Обычные настройки';
$string['operation'] = 'Операция';
$string['operation_rename'] = 'Нормализовать названия (найти/заменить)';
$string['operation_rollback'] = 'Откат предыдущей операции';
$string['operation_save_dates'] = 'Сохранить отредактированные даты';
$string['operation_session_schedule'] = 'Планирование дат сессии';
$string['operation_shift_dates'] = 'Сдвинуть настроенные даты';
$string['operation_visibility'] = 'Изменить видимость';
$string['operationhistory'] = 'История операций с активностями';
$string['operationpreview'] = 'Предпросмотр операции';
$string['overridechecks'] = 'Индивидуальные и групповые переопределения';
$string['period_exam'] = 'Экзамен';
$string['period_resit1'] = 'Пересдача 1';
$string['period_resit2'] = 'Пересдача 2';
$string['periodclose'] = 'Закрытие / срок сдачи';
$string['periodopen'] = 'Открытие / начало приёма';
$string['perpage'] = 'Строк на странице';
$string['pluginname'] = 'Центр контроля качества курсов';
$string['positivevalueexpected'] = 'Значение не может быть отрицательным.';
$string['preparerollback'] = 'Подготовить безопасный откат';
$string['present'] = 'Есть';
$string['previewchanges'] = 'Предпросмотр изменений';
$string['previewconflictnote'] = 'Перед изменением каждого элемента плагин повторно сравнивает его значения с предпросмотром. ' .
    'Если преподаватель изменил элемент после создания предпросмотра, элемент будет пропущен, а ' .
    'новое изменение преподавателя не затрётся.';
$string['privacy:metadata'] = 'Центр контроля качества хранит сведения о запусках аудита, найденных проблемах конфигурации ' .
    'и истории операций с активностями. Ответы и работы студентов не сохраняются.';
$string['privacy:metadata:activitybatch'] = 'Пакеты операций с активностями';
$string['privacy:metadata:activitybatch:userid'] = 'Пользователь, создавший операцию с активностями';
$string['privacy:metadata:exception'] = 'Исключения из правил соответствия, созданные администраторами';
$string['privacy:metadata:exception:reason'] = 'Административная причина исключения';
$string['privacy:metadata:exception:userid'] = 'Пользователь, создавший исключение';
$string['privacy:metadata:scan'] = 'Запуски аудита';
$string['privacy:metadata:timecreated'] = 'Время создания аудита';
$string['privacy:metadata:userid'] = 'Пользователь, запустивший аудит';
$string['problem'] = 'Проблема';
$string['profile'] = 'Профиль контроля';
$string['profiledescription'] = 'Описание';
$string['profilename'] = 'Название профиля';
$string['profilepreset_credit'] = 'Зачёт — базовый стандарт';
$string['profilepreset_credit_desc'] = 'Базовый профиль для дисциплин с зачётом/зачётным тестом (idnumber: credit).';
$string['profilepreset_exam'] = 'Экзамен — базовый стандарт';
$string['profilepreset_exam_desc'] = 'Базовый профиль для дисциплин с экзаменом. Требует exam и включает консервативные проверки ' .
    'безопасности экзамена.';
$string['profilepreset_practice'] = 'Практика — базовый стандарт';
$string['profilepreset_practice_desc'] = 'Профиль проверок курса для практик, где обязательный Quiz exam не требуется.';
$string['profiles'] = 'Профили стандартов';
$string['profilesaved'] = 'Профиль стандарта сохранён.';
$string['profilesintro'] = 'Профили задают ожидаемые настройки курса и контрольных мероприятий. Можно использовать ' .
    'разные стандарты для экзамена, зачёта, практики и других типов курсов.';
$string['profiletargets'] = 'Целевые контрольные мероприятия';
$string['quizchecks'] = 'Проверки тестов';
$string['quizclose'] = 'Закрытие';
$string['quizgradeitemlocked'] = 'Элемент оценки этого теста заблокирован, поэтому даты теста не изменены.';
$string['quizidnumber'] = 'idnumber теста';
$string['quizopen'] = 'Открытие';
$string['readinesscontext'] = '{$a->category} · {$a->profile} · общая готовность контрольных мероприятий: {$a->score}%';
$string['readinessscore'] = 'Готовность';
$string['ready'] = 'Готово';
$string['recentscans'] = 'Последние проверки';
$string['replacetext'] = 'Заменить на';
$string['report'] = 'Отчёт контроля качества';
$string['requirecompletion'] = 'Требовать включённое отслеживание выполнения курса';
$string['requireexam'] = 'Требовать основной экзамен/контроль';
$string['requiregradepass'] = 'Требовать проходной балл';
$string['requirepassword'] = 'Требовать пароль теста';
$string['requireresit1'] = 'Требовать пересдачу 1';
$string['requireresit2'] = 'Требовать пересдачу 2';
$string['requireshuffleanswers'] = 'Требовать перемешивание вариантов ответов';
$string['requiresubnet'] = 'Требовать ограничение по IP/подсети';
$string['requireteacher'] = 'Требовать хотя бы одного преподавателя';
$string['resit1idnumber'] = 'idnumber пересдачи 1';
$string['resit2idnumber'] = 'idnumber пересдачи 2';
$string['restrictions'] = 'Ограничения доступа';
$string['restrictions_configured'] = 'Ограничения настроены';
$string['restrictions_none'] = 'Без ограничений';
$string['retentiondays'] = 'Хранение результатов, дней';
$string['retentiondays_desc'] = 'Результаты аудита и история операций старше указанного срока удаляются плановой задачей.';
$string['reviewchecks'] = 'Параметры просмотра результатов';
$string['rollbackofrollback'] = 'Операцию отката нельзя использовать как источник ещё одного отката.';
$string['rollbackpreviewcreated'] = 'Предпросмотр отката создан. Проверьте изменения перед применением.';
$string['rule'] = 'Правило';
$string['scanfailed'] = 'Проверка завершилась ошибкой';
$string['scanqueued'] = 'Проверка поставлена в очередь и будет выполнена Moodle cron в фоне.';
$string['scanrunning'] = 'Проверка ожидает выполнения или уже выполняется. Обновите страницу после запуска cron.';
$string['scheduleperiod'] = 'Изменить этот период';
$string['section'] = 'Раздел';
$string['sectionnum'] = 'Раздел {$a}';
$string['selectionlimit'] = 'За одну операцию можно выбрать от 1 до 200 элементов.';
$string['selectperiodrequired'] = 'Выберите хотя бы один период: экзамен или пересдачу.';
$string['selectrowsfirst'] = 'Выберите до 200 элементов на этой странице. На следующем шаге будет показан точный ' .
    'предпросмотр, и только потом изменения можно подтвердить.';
$string['sessiondefaultcategory'] = 'Категория по умолчанию для планирования сессии';
$string['sessiondefaultcategory_desc'] = 'Эта категория будет заранее выбрана при открытии «Планирования сессии». ' .
    'При миграции, если ' .
    'возможно, используется прежняя категория по умолчанию local_examdates.';
$string['sessionissue_duplicate'] = 'Найдено несколько элементов с таким idnumber ({$a}). Цель пропущена.';
$string['sessionissue_missing'] = 'Целевой элемент не найден.';
$string['sessionissue_nopermission'] = 'У пользователя нет права изменять этот элемент.';
$string['sessionissues'] = 'Проблемы целевых элементов';
$string['sessionissuesnotice'] = 'Найдено проблем с целевыми элементами: {$a}. Они не включены в пакет изменений.';
$string['sessionplanning'] = 'Планирование сессии';
$string['sessionplanning_intro'] = 'Массово задаёт окна экзамена и пересдач для всех курсов выбранной категории. Целевые ' .
    'элементы определяются по idnumber, сначала формируется безопасный предпросмотр без ' .
    'изменений Moodle, затем изменения применяются единым фоновым batch-движком с проверкой ' .
    'конфликтов, журналом и безопасным откатом.';
$string['sessionreadiness'] = 'Готовность к сессии';
$string['sessiontargetrequired'] = 'Укажите хотя бы один idnumber теста или задания для этого периода.';
$string['settings'] = 'Настройки контроля качества курсов';
$string['severity'] = 'Важность';
$string['severity_critical'] = 'Критическая';
$string['severity_notice'] = 'Замечание';
$string['severity_warning'] = 'Предупреждение';
$string['shiftamount'] = 'Величина сдвига';
$string['shiftunit'] = 'Единица';
$string['shiftunit_day'] = 'день';
$string['shiftunit_hour'] = 'час';
$string['shiftunit_month'] = 'месяц';
$string['shiftunit_week'] = 'неделя';
$string['shiftzeronotallowed'] = 'Величина сдвига дат не может быть равна нулю.';
$string['skipped'] = 'Пропущено';
$string['sourcebatch'] = 'Исходная операция';
$string['startscan'] = 'Запустить проверку';
$string['status'] = 'Статус';
$string['status_complete'] = 'Завершена';
$string['status_failed'] = 'Ошибка';
$string['status_queued'] = 'В очереди';
$string['status_running'] = 'Выполняется';
$string['stealthdisabled'] = 'Режим «доступен, но не показан» отключён в настройках этого Moodle.';
$string['taskcleanup'] = 'Очистка старых результатов контроля качества';
$string['topassessmentproblems'] = 'Самые частые проблемы контрольных мероприятий';
$string['total'] = 'Всего';
$string['unlimited'] = 'Неограниченно';
$string['unlocked'] = 'Не заблокировано';
$string['v_course_completion'] = 'Отслеживание выполнения курса отключено.';
$string['v_course_dates'] = 'Дата окончания курса не позже даты начала.';
$string['v_course_startdate'] = 'Не задана дата начала курса.';
$string['v_course_teacher'] = 'В контексте курса не найден преподаватель или редактирующий преподаватель, назначенный ' .
    'непосредственно в курс.';
$string['v_quiz_attempts'] = 'Количество попыток экзамена не соответствует профилю.';
$string['v_quiz_browsersecurity'] = 'Настройка безопасности браузера отличается от стандарта профиля.';
$string['v_quiz_close'] = 'Не задана дата закрытия теста.';
$string['v_quiz_correctness_open'] = 'Правильность ответа может показываться сразу после попытки или позднее, пока тест ещё ' .
    'открыт.';
$string['v_quiz_dates'] = 'Дата закрытия теста не позже даты открытия.';
$string['v_quiz_duplicate_target'] = 'В курсе найдено несколько тестов с одинаковым целевым idnumber.';
$string['v_quiz_grade'] = 'Максимальная оценка теста равна нулю или некорректна.';
$string['v_quiz_grade_standard'] = 'Максимальная оценка теста отличается от стандарта профиля.';
$string['v_quiz_gradebook_max'] = 'Максимальная оценка Quiz не совпадает с максимумом элемента в журнале оценок.';
$string['v_quiz_gradeitem_locked'] = 'Элемент оценки теста заблокирован в журнале оценок.';
$string['v_quiz_gradeitem_missing'] = 'Элемент оценки теста отсутствует в журнале оценок.';
$string['v_quiz_gradepass'] = 'Для экзамена не задан проходной балл.';
$string['v_quiz_gradepass_invalid'] = 'Проходной балл выше максимальной оценки Quiz.';
$string['v_quiz_gradepass_min'] = 'Проходной балл ниже минимального значения профиля.';
$string['v_quiz_open'] = 'Не задана дата открытия теста.';
$string['v_quiz_override_attempts'] = 'Одно из переопределений разрешает больше попыток, чем допускает профиль.';
$string['v_quiz_override_close'] = 'Одно из переопределений продлевает закрытие теста больше, чем допускает профиль.';
$string['v_quiz_overrides'] = 'У теста есть индивидуальные или групповые переопределения, которые следует проверить.';
$string['v_quiz_password'] = 'Профиль требует пароль теста, но пароль не настроен.';
$string['v_quiz_questions'] = 'В тесте отсутствуют слоты вопросов.';
$string['v_quiz_questions_max'] = 'В тесте больше вопросов, чем допускает профиль.';
$string['v_quiz_questions_min'] = 'В тесте меньше вопросов, чем требует профиль.';
$string['v_quiz_required'] = 'Не найден обязательный элемент контроля с idnumber «{$a}».';
$string['v_quiz_rightanswer_open'] = 'Правильные ответы могут показываться сразу после попытки или позднее, пока тест ещё открыт.';
$string['v_quiz_shuffleanswers'] = 'Профиль требует перемешивание ответов, но оно отключено.';
$string['v_quiz_subnet'] = 'Профиль требует ограничение по IP/подсети, но оно не настроено.';
$string['v_quiz_sumgrades'] = 'Сумма баллов вопросов Quiz (sumgrades) равна 0.';
$string['v_quiz_timelimit'] = 'Для экзамена не задано ограничение времени.';
$string['v_quiz_timelimit_standard'] = 'Ограничение времени теста отличается от стандарта профиля.';
$string['v_quiz_zero_marks'] = 'Один или несколько вопросов/слотов теста имеют максимальный балл 0.';
$string['view'] = 'Открыть';
$string['viewreport'] = 'Открыть отчёт';
$string['viewsessionissues'] = 'Посмотреть проблемы';
$string['visibility'] = 'Видимость';
$string['visibility_hide'] = 'Скрыт';
$string['visibility_show'] = 'Показывается на странице курса';
$string['visibility_stealth'] = 'Доступен, но не показан на странице курса';
$string['warnings'] = 'Предупреждения';
$string['warnoverrides'] = 'Показывать наличие user/group overrides';
