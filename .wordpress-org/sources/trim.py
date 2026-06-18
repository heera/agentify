import sys
from PIL import Image

inp, outp = sys.argv[1], sys.argv[2]
im = Image.open(inp).convert('RGB')
w, h = im.size
px = im.load()
bg = px[2, h - 2]  # page background (bottom-left)

def close(a, b, tol=8):
    return all(abs(a[i] - b[i]) <= tol for i in range(3))

def row_is_bg(y):
    for x in range(0, w, 6):
        if not close(px[x, y], bg):
            return False
    return True

y = h - 1
while y > 0 and row_is_bg(y):
    y -= 1
bottom = min(h, y + 1 + 30)  # keep ~30px breathing room below content
im.crop((0, 0, w, bottom)).save(outp)
print("%s -> %dx%d" % (outp, w, bottom))
