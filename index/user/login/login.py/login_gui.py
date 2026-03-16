import bcrypt
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
import customtkinter as ctk 
from tkinter import messagebox
import webbrowser          # Used to open the web browser
import os

# ---------------------------- Database Configuration ----------------------------
DB_USER = "root"
DB_PASSWORD = ""
DB_HOST = "localhost"
DB_NAME = "KeyManager"

# Create the database URL for SQLAlchemy
DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}"
engine = create_engine(DATABASE_URL, echo=False)
Session = sessionmaker(bind=engine)

# ---------------------------- Login Validation Function ----------------------------
def attempt_login():
    """
    Attempt to log in the user by verifying the email and password against the database.
    Shows appropriate message boxes for errors or success.
    """
    email = email_entry.get().strip()
    password = pass_entry.get().strip()

    # Check if both fields are filled
    if not email or not password:
        messagebox.showwarning("Warning", "Please fill in all fields")
        return

    session = Session()
    try:
        # Query the user with the given email
        result = session.execute(
            text("SELECT * FROM users WHERE email = :email"),
            {"email": email}
        ).fetchone()

        if not result:
            messagebox.showerror("Error", "User does not exist")
            return

        # Check if the email has been verified
        if result.email_verified == 0:
            messagebox.showwarning("Warning", "Email not verified")
            return

        # Verify the password using bcrypt
        stored_hash = result.password_hash_master.encode()
        if bcrypt.checkpw(password.encode(), stored_hash):
            messagebox.showinfo("Welcome", f"Login successful! Welcome {result.username}")
        else:
            messagebox.showerror("Error", "Incorrect password")

    except Exception as e:
        messagebox.showerror("Connection Error", f"Failed to connect to the database: {e}")
    finally:
        session.close()

# ---------------------------- LOGIN GUI ----------------------------
# Set the appearance mode to follow the system theme (light/dark)
ctk.set_appearance_mode("system")
# Set the color theme to blue
ctk.set_default_color_theme("blue")

# Create the main application window
root = ctk.CTk()
root.title("KeyManager · Login")
root.geometry("380x480")
root.resizable(False, False)

# Main frame container with transparent background
main_frame = ctk.CTkFrame(root, fg_color="transparent")
main_frame.pack(pady=40, padx=30, fill="both", expand=True)

# Icon label with lock emoji
logo_label = ctk.CTkLabel(
    main_frame,
    text="KEYMANAGER",
    font=ctk.CTkFont(size=48, weight="bold"),
    text_color=("#2B2B2B", "#E0E0E0")  # Dark mode and light mode colors
)
logo_label.pack(pady=(0, 10))

# Welcome text label
welcome_label = ctk.CTkLabel(
    main_frame,
    text="Welcome Back",
    font=ctk.CTkFont(size=24, weight="bold"),
    text_color=("#1E1E1E", "#F5F5F5")
)
welcome_label.pack(pady=(0, 5))

# Subtitle text
sub_label = ctk.CTkLabel(
    main_frame,
    text="Continue with your account",
    font=ctk.CTkFont(size=13),
    text_color=("gray40", "gray70")
)
sub_label.pack(pady=(0, 25))

# Email entry input field
email_entry = ctk.CTkEntry(
    main_frame,
    placeholder_text="Email",
    width=280,
    height=45,
    corner_radius=12,
    border_width=1.5,
    font=ctk.CTkFont(size=14)
)
email_entry.pack(pady=(0, 15))

# Password entry input field with hidden characters
pass_entry = ctk.CTkEntry(
    main_frame,
    placeholder_text="Password",
    width=280,
    height=45,
    corner_radius=12,
    border_width=1.5,
    show="●",
    font=ctk.CTkFont(size=14)
)
pass_entry.pack(pady=(0, 25))

# Login button to trigger the login attempt
login_btn = ctk.CTkButton(
    main_frame,
    text="Login",
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

# Bottom frame for auxiliary options
bottom_frame = ctk.CTkFrame(main_frame, fg_color="transparent")
bottom_frame.pack(pady=(10, 0))

# "Forgot Password?" label that shows an info popup when clicked
forget_btn = ctk.CTkLabel(
    bottom_frame,
    text="Forgot Password?",
    font=ctk.CTkFont(size=13, underline=True),
    text_color=("#007AFF", "#6AB0FF"),
    cursor="hand2"
)
forget_btn.pack(side="left", padx=(0, 20))
forget_btn.bind("<Button-1>", lambda e: messagebox.showinfo("Info", "Please contact the administrator to reset your password"))

# "Create Account" label that opens the register page in a browser when clicked
register_btn = ctk.CTkLabel(
    bottom_frame,
    text="Create Account",
    font=ctk.CTkFont(size=13, underline=True),
    text_color=("#007AFF", "#6AB0FF"),
    cursor="hand2"
)
register_btn.pack(side="left")
register_btn.bind("<Button-1>", lambda e: webbrowser.open("http://localhost/project-work/register/register.php"))

# Footer label at the bottom of the main frame
footer_label = ctk.CTkLabel(
    main_frame,
    text="KeyManager  ·  Secure Login",
    font=ctk.CTkFont(size=11),
    text_color=("gray50", "gray60")
)
footer_label.pack(side="bottom", pady=(20, 0))

# Start the GUI event loop
root.mainloop()