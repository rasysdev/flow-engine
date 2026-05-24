from fastapi import APIRouter

router = APIRouter()


@router.get("/clients")
def list_clients():
    return []


@router.post("/backup/run")
def run_backup(data: dict):
    return {"status": "ok"}


def internal_helper():
    return True


class BackupService:
    @router.delete("/backup/{backup_id}")
    def delete_backup(self, backup_id: int):
        return {"deleted": backup_id}
