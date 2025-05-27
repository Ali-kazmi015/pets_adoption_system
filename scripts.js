// scripts.js

// Alert for surrender form
const surrenderForm = document.getElementById('surrenderForm');
surrenderForm?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Thank you for surrendering your pet. We will take good care of it.');
    surrenderForm.reset();
});

// Alert for feedback form

// Alert for login
const loginForm = document.getElementById('loginForm');
loginForm?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Login attempt submitted!');
    loginForm.reset();
});

// Alert for signup
const signupForm = document.getElementById('signupForm');
signupForm?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Signup successful! Please login to continue.');
    signupForm.reset();
});

// Alert for adoption request
// const adoptionForm = document.getElementById('adoptionForm');
// adoptionForm?.addEventListener('submit', function(e) {
//     e.preventDefault();
//     alert('Adoption request submitted successfully!');
//     adoptionForm.reset();
// });

// Alert for lost and found form
const lostFoundForm = document.getElementById('lostFoundForm');
lostFoundForm?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Your report has been submitted. Thank you for helping!');
    lostFoundForm.reset();
});

// Alert for profile update form
const profileForm = document.getElementById('profileForm');
profileForm?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Profile updated successfully!');
    profileForm.reset();
});

// Populate pets dynamically (sample data)
const petGallery = document.getElementById('petGallery');
const pets = [
    { name: 'Buddy', species: 'Dog', image: 'retriever_dog.jpg' },
    { name: 'Hazel', species: 'Cat', image: 'Hazel2.jpg' },
    { name: 'Nibbles', species: 'Rabbit', image: 'rabbit.jpg' },
    { name: 'Whiskers', species: 'Cat', image: 'siamese.jpeg' },
    {name: 'Swifty',species : 'Cat',image :'Swifty_kitten.jpg'},
    {name: 'Elsa & Ana ',species : 'Cat',image :'2cats.jpg'},
    {name: 'jacky',species : 'Dog',image :'Russian_dog.jpg'},
    {name: 'Bella ',species : ' Dog',image :'Australian_Shepherd.jpg'}

];

if (petGallery) {
    pets.forEach(pet => {
        const div = document.createElement('div');
        div.className = 'pet-card';
        div.innerHTML = `<img src="${pet.image}" alt="${pet.name}"><h3>${pet.name}</h3><p>${pet.species}</p>`;
        petGallery.appendChild(div);
    });
}
