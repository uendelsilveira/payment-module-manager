import json
import re
import sys
import os

# -------------------------------------------------------------
# VARIABLES THE SETTINGS
# -------------------------------------------------------------
TASK_JSON_PATH = ".taskmaster/tasks/tasks.json"
MARKDOWN_PATH = ".taskmaster/docs/new-implements-26112025.md"
# -------------------------------------------------------------


# ============================================================
# DESCRIPTION GENERATOR
# ============================================================
def generate_professional_description(title: str) -> str:
    return (
        f"Esta tarefa tem como objetivo implementar, revisar ou aprimorar o item "
        f"**{title}**, garantindo aderência aos requisitos funcionais, técnicos e "
        f"de qualidade do projeto. Inclui análise, desenvolvimento, validação, "
        f"documentação e ajustes necessários para sua correta entrega."
    )


# ===============================
# CLI - PARAMETERS
# ===============================
if len(sys.argv) == 3:
    TASK_JSON_PATH = sys.argv[1]
    MARKDOWN_PATH = sys.argv[2]
elif len(sys.argv) != 1:
    print("✅ Uso correto:")
    print("python3 import-tasks.py /caminho/task.json /caminho/tarefas.md")
    sys.exit(1)

print("📌 Usando arquivos:")
print("   JSON:", TASK_JSON_PATH)
print("   MD  :", MARKDOWN_PATH)

# ===============================
# VERIFICATION FILES
# ===============================
if not os.path.exists(TASK_JSON_PATH):
    print(f"❌ ERRO: task.json não encontrado: {TASK_JSON_PATH}")
    sys.exit(1)

if not os.path.exists(MARKDOWN_PATH):
    print(f"❌ ERRO: markdown não encontrado: {MARKDOWN_PATH}")
    sys.exit(1)

with open(TASK_JSON_PATH, "r", encoding="utf-8") as f:
    data = json.load(f)

# === JSON STRUCTURE ===
if "master" not in data or "tasks" not in data["master"]:
    print("❌ ERRO: Estrutura esperada: { 'master': { 'tasks': [] } }")
    sys.exit(1)

tasks = data["master"]["tasks"]


# ============================================================
# COLLECTOR IDS
# ============================================================
existing_ids = set()

def collect_ids(items):
    for t in items:
        if "id" in t:
            existing_ids.add(int(t["id"]))
        for st in t.get("subtasks", []):
            try:
                sid = int(str(st["id"]).split(".")[0])
                existing_ids.add(sid)
            except:
                pass

collect_ids(tasks)
next_id = max(existing_ids) + 1 if existing_ids else 1


# ============================================================
# MARKDOWN PARSER
# ============================================================
def parse_markdown(md: str):
    tasks = []
    blocks = re.split(r"(?m)^## ", md)

    for block in blocks[1:]:
        lines = block.strip().split("\n")

        title = lines[0].strip()

        subtasks = []
        for line in lines[1:]:
            line = line.strip()
            if line.startswith("- "):
                subtasks.append(line[2:].strip())

        tasks.append({
            "title": title,
            "subtasks": subtasks
        })

    return tasks


with open(MARKDOWN_PATH, "r", encoding="utf-8") as f:
    md = f.read()

parsed_tasks = parse_markdown(md)


# ============================================================
# Add tasks ao JSON
# ============================================================
for t in parsed_tasks:

    new_task = {
        "id": next_id,
        "title": t["title"],
        "description": generate_professional_description(t["title"]),
        "details": "",
        "testStrategy": "",
        "priority": "medium",
        "dependencies": [],
        "status": "pending",
        "subtasks": []
    }

    # Create subtasks (ID.Task)
    for index, sub in enumerate(t["subtasks"], start=1):
        new_task["subtasks"].append({
            "id": f"{next_id}.{index}",
            "title": sub,
            "status": "pending"
        })

    tasks.append(new_task)
    next_id += 1


# ============================================================
# Save
# ============================================================
with open(TASK_JSON_PATH, "w", encoding="utf-8") as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("✅ Importação concluída!")
print(f"→ {len(parsed_tasks)} novas tasks adicionadas")
print(f"→ Arquivo atualizado: {TASK_JSON_PATH}")
