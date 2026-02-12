import bcrypt
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
import customtkinter as ctk
from tkinter import messagebox
import webbrowser          # 新增：用于打开浏览器
import os

# ---------------------------- 数据库配置 ----------------------------
DB_USER = "root"
DB_PASSWORD = ""
DB_HOST = "127.0.0.1"
DB_NAME = "KeyManager"

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}"
engine = create_engine(DATABASE_URL, echo=False)
Session = sessionmaker(bind=engine)

# ---------------------------- 登录验证函数 ----------------------------
def attempt_login():
    email = email_entry.get().strip()
    password = pass_entry.get().strip()

    if not email or not password:
        messagebox.showwarning("注意", "请填写所有字段")
        return

    session = Session()
    try:
        result = session.execute(
            text("SELECT * FROM users WHERE email = :email"),
            {"email": email}
        ).fetchone()

        if not result:
            messagebox.showerror("错误", "用户不存在")
            return

        if result.email_verified == 0:
            messagebox.showwarning("警告", "邮箱未验证")
            return

        stored_hash = result.password_hash_master.encode()
        if bcrypt.checkpw(password.encode(), stored_hash):
            messagebox.showinfo("欢迎", f"登录成功！欢迎 {result.username}")
        else:
            messagebox.showerror("错误", "密码错误")

    except Exception as e:
        messagebox.showerror("连接错误", f"数据库连接失败: {e}")
    finally:
        session.close()

# ---------------------------- 苹果科技感登录界面 ----------------------------
ctk.set_appearance_mode("system")
ctk.set_default_color_theme("blue")

root = ctk.CTk()
root.title("KeyManager · 登录")
root.geometry("380x480")
root.resizable(False, False)

# 主框架
main_frame = ctk.CTkFrame(root, fg_color="transparent")
main_frame.pack(pady=40, padx=30, fill="both", expand=True)

# 图标
logo_label = ctk.CTkLabel(
    main_frame,
    text="🔐",
    font=ctk.CTkFont(size=48, weight="bold"),
    text_color=("#2B2B2B", "#E0E0E0")
)
logo_label.pack(pady=(0, 10))

# 欢迎文字
welcome_label = ctk.CTkLabel(
    main_frame,
    text="欢迎回来",
    font=ctk.CTkFont(size=24, weight="bold"),
    text_color=("#1E1E1E", "#F5F5F5")
)
welcome_label.pack(pady=(0, 5))

sub_label = ctk.CTkLabel(
    main_frame,
    text="使用您的账号继续",
    font=ctk.CTkFont(size=13),
    text_color=("gray40", "gray70")
)
sub_label.pack(pady=(0, 25))

# 邮箱输入框
email_entry = ctk.CTkEntry(
    main_frame,
    placeholder_text="电子邮箱",
    width=280,
    height=45,
    corner_radius=12,
    border_width=1.5,
    font=ctk.CTkFont(size=14)
)
email_entry.pack(pady=(0, 15))

# 密码输入框
pass_entry = ctk.CTkEntry(
    main_frame,
    placeholder_text="密码",
    width=280,
    height=45,
    corner_radius=12,
    border_width=1.5,
    show="●",
    font=ctk.CTkFont(size=14)
)
pass_entry.pack(pady=(0, 25))

# 登录按钮
login_btn = ctk.CTkButton(
    main_frame,
    text="登录",
    width=280,
    height=45,
    corner_radius=12,
    font=ctk.CTkFont(size=15, weight="bold"),
    command=attempt_login,
    fg_color="#007AFF",
    hover_color="#005BBB",
    text_color="white"
)
login_btn.pack(pady=(0, 20))

# 辅助功能行
bottom_frame = ctk.CTkFrame(main_frame, fg_color="transparent")
bottom_frame.pack(pady=(10, 0))

# 忘记密码（仍使用弹窗提示）
forget_btn = ctk.CTkLabel(
    bottom_frame,
    text="忘记密码?",
    font=ctk.CTkFont(size=13, underline=True),
    text_color=("#007AFF", "#6AB0FF"),
    cursor="hand2"
)
forget_btn.pack(side="left", padx=(0, 20))
forget_btn.bind("<Button-1>", lambda e: messagebox.showinfo("提示", "请联系管理员重置密码"))

# 创建账户 —— 现在会打开您的 register.php
register_btn = ctk.CTkLabel(
    bottom_frame,
    text="创建账户",
    font=ctk.CTkFont(size=13, underline=True),
    text_color=("#007AFF", "#6AB0FF"),
    cursor="hand2"
)
register_btn.pack(side="left")
register_btn.bind("<Button-1>", lambda e: webbrowser.open("http://localhost/project-work/register/register.php"))

# 脚注
footer_label = ctk.CTkLabel(
    main_frame,
    text="KeyManager  ·  安全登录",
    font=ctk.CTkFont(size=11),
    text_color=("gray50", "gray60")
)
footer_label.pack(side="bottom", pady=(20, 0))

root.mainloop()