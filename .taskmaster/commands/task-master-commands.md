# Task Master CLI Commands

## Parse a PRD file

    task-master parse-prd <prd-file.txt>
    task-master parse-prd <prd-file.txt> --num-tasks=10

## List tasks

    task-master list
    task-master list --status=<status>
    task-master list --with-subtasks
    task-master list --status=<status> --with-subtasks

## Show next task

    task-master next

## Show task details

    task-master show <id>
    task-master show --id=<id>
    task-master show 1.2

## Update tasks

    task-master update --from=<id> --prompt="<prompt>"
    task-master update-task --id=<id> --prompt="<prompt>"
    task-master update-task --id=<id> --prompt="<prompt>" --research
    task-master update-subtask --id=<parentId.subtaskId> --prompt="<prompt>"
    task-master update-subtask --id=<parentId.subtaskId> --prompt="<prompt>" --research

## Generate task files

    task-master generate

## Set status

    task-master set-status --id=<id> --status=<status>
    task-master set-status --id=1,2,3 --status=<status>
    task-master set-status --id=1.1,1.2 --status=<status>

## Expand tasks

    task-master expand --id=<id> --num=<number>
    task-master expand --id=<id> --prompt="<context>"
    task-master expand --all
    task-master expand --all --force
    task-master expand --id=<id> --research
    task-master expand --all --research

## Clear subtasks

    task-master clear-subtasks --id=<id>
    task-master clear-subtasks --id=1,2,3
    task-master clear-subtasks --all

## Complexity analysis

    task-master analyze-complexity
    task-master analyze-complexity --output=my-report.json
    task-master analyze-complexity --model=claude-3-opus-20240229
    task-master analyze-complexity --threshold=6
    task-master analyze-complexity --file=custom-tasks.json
    task-master analyze-complexity --research

## Display complexity report

    task-master complexity-report
    task-master complexity-report --file=my-report.json

## Manage dependencies

    task-master add-dependency --id=<id> --depends-on=<id>
    task-master remove-dependency --id=<id> --depends-on=<id>
    task-master validate-dependencies
    task-master fix-dependencies

## Add tasks

    task-master add-task --prompt="Description of the new task"
    task-master add-task --prompt="Description" --dependencies=1,2,3
    task-master add-task --prompt="Description" --priority=high

## Initialize project

    task-master init
