# Performance and reliability testing

CourseCompliance 0.18 records basic metrics for every scan worker and aggregates them into the parent audit:

- total worker execution time;
- database query count;
- peak PHP memory used by the heaviest worker;
- worker retry count.

## Recommended load-test ladder

Use a representative standard with Question Bank integrity enabled and increase the selected course scope gradually:

1. 50 courses;
2. 100 courses;
3. 250 courses;
4. 500 courses;
5. 1000 courses only after the previous run is stable.

Start with 10 courses per background worker. If peak memory remains low and tasks complete comfortably inside your cron/worker limits, compare 20-course workers. Deep Question Bank audits should normally use smaller workers than configuration-only audits.

## Failure recovery

A failed worker is automatically requeued according to **CourseCompliance → Settings → Automatic retries for a failed worker**. The default is one retry. If retries are exhausted, the parent audit is marked failed while successful worker results remain stored. Open the failed report and use **Retry failed batches** to requeue only failed workers.

Do not delete failed child scans manually: they are required for targeted recovery and diagnostics.
