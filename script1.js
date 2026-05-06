const user = JSON.parse(localStorage.getItem("user"));
const guestView = document.getElementById("guestView");
const userView = document.getElementById("userView");
const welcomeText = document.getElementById("welcome");
const adminLink = document.getElementById("adminLink");

if(user){
    guestView.style.display = "none";
    userView.style.display = "block";
    welcomeText.textContent = "welcome"+ user.username;

    if(user.role === "admin"){
        adminLink.style.display = "inline-block";
    }
}
else{
    guestView.style.display = "block";
    userView.style.display = "none"; 
}
function logout(){
    localStorage.removeItem(user);
    location.reload;
}