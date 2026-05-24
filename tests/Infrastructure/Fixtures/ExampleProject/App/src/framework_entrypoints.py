"""
Fixture file for v4.10 Python framework entrypoint detection tests.

Covers: Flask routes, FastAPI HTTP methods, Django CBVs,
        Click CLI commands, Typer commands, Celery tasks,
        __main__ block, and Python dunder methods.
"""
from flask import Flask
from celery import shared_task
import click

app = Flask(__name__)
celery_app = None  # mock reference


# --- Flask routes ---

@app.route("/users")
def list_users():
    return []


@app.route("/users/<int:user_id>", methods=["GET", "PUT"])
def user_detail(user_id: int):
    return {}


@app.route("/users/<int:user_id>", methods=["DELETE"])
def delete_user(user_id: int):
    return {}


# --- Click CLI commands ---

@click.command()
def sync():
    pass


@click.group()
def cli():
    pass


# --- Typer / generic .command() ---

@app.command()
def deploy():
    pass


# --- Celery tasks ---

@shared_task
def send_email(to: str, subject: str):
    pass


@celery_app.task
def process_data(data: dict):
    pass


@shared_task(bind=True)
def retry_task(self, payload: dict):
    pass


# --- Django CBV (class name contains "View") ---

class UserView:
    def get(self, request, pk: int):
        return {}

    def post(self, request):
        return {}

    def non_http_method(self):
        pass


class ProductViewSet:
    def get(self, request):
        return []

    def dispatch(self, request):
        pass


# --- Django CBV via parent class name ---

class OrderDetail(DetailView):
    def get(self, request, pk: int):
        return {}


# --- Plain class (not a View — HTTP methods should NOT be marked as entrypoints) ---

class DataProcessor:
    def get(self, key: str):
        return None

    def process(self):
        pass


# --- Dunder methods (never orphans) ---

class DataModel:
    def __str__(self):
        return "model"

    def __repr__(self):
        return "DataModel()"

    def __eq__(self, other):
        return True

    def __len__(self):
        return 0

    def __iter__(self):
        return iter([])

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        pass

    def __call__(self):
        pass

    def __hash__(self):
        return 0


# --- Regular internal helper (should be an orphan if never called) ---

def internal_helper():
    return True


# --- __main__ entry block ---

if __name__ == '__main__':
    sync()
