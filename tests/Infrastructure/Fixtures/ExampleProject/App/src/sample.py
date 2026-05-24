def b():
    return 1


def a():
    x = b()
    return x


class C:
    def m(self):
        return a()

