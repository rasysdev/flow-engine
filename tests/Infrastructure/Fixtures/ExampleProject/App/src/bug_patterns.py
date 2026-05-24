"""
Python bug-pattern fixture for PythonBugScannerTest.
Each section exercises one detected pattern.
"""

import os
import subprocess

# ---------------------------------------------------------------------------
# Exception Swallowing
# ---------------------------------------------------------------------------

def swallow_bare_except():
    try:
        dangerous()
    except:
        pass


def swallow_exception_class():
    try:
        dangerous()
    except Exception:
        pass


def swallow_with_named_var():
    try:
        dangerous()
    except Exception as e:
        pass


def swallow_only_comment():
    try:
        dangerous()
    except Exception:
        # TODO: handle this properly
        pass


def proper_exception_handling():
    try:
        dangerous()
    except Exception as e:
        print(f"Error: {e}")
        raise


# ---------------------------------------------------------------------------
# Mutable Default Arguments
# ---------------------------------------------------------------------------

def mutable_list_default(items=[]):
    return items


def mutable_dict_default(config={}):
    return config


def mutable_nonempty_list(items=[1, 2, 3]):
    return items


def immutable_none_default(items=None):
    return items or []


def immutable_string_default(label="default"):
    return label


# ---------------------------------------------------------------------------
# Missing Return Type
# ---------------------------------------------------------------------------

def public_no_type(x, y):
    return x + y


def also_public(name):
    return f"Hello {name}"


def _private_no_type(x):
    return x


def __dunder_like__(self):
    return str(self)


def typed_function(x: int) -> int:
    return x * 2


def typed_none(name: str) -> None:
    print(name)


# ---------------------------------------------------------------------------
# Unchecked Subprocess
# ---------------------------------------------------------------------------

def run_unchecked_os():
    os.system("ls -la")


def run_unchecked_subprocess():
    subprocess.run(["git", "status"])


def run_unchecked_call():
    subprocess.call(["make", "build"])


def checked_subprocess():
    result = subprocess.run(["git", "status"], capture_output=True)
    return result


def assigned_os():
    rc = os.system("ls")
    return rc
