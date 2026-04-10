const { useEffect, useState } = React;

// put your uploaded image here
const logo = "images/robot2.png.png";
const heroImage = "images/hero.png";

function useReveal() {
  useEffect(() => {
    const items = document.querySelectorAll(".reveal");

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("active");
          }
        });
      },
      { threshold: 0.15 }
    );

    items.forEach((item) => observer.observe(item));
    return () => observer.disconnect();
  }, []);
}

function useNavbarScroll() {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener("scroll", onScroll);
    onScroll();
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return scrolled;
}

const features = [
  {
    icon: "🎯",
    title: "Age-Based Learning Paths",
    text: "Programs are built for ages 6 to 18 so every student learns at the right level and pace."
  },
  {
    icon: "💻",
    title: "Real Coding Practice",
    text: "Students build games, websites, mini apps, and logical projects instead of only watching theory."
  },
  {
    icon: "🤖",
    title: "Robotics Experience",
    text: "Kids and juniors explore robotics thinking, control systems, and creative technology challenges."
  },
  {
    icon: "👩‍🏫",
    title: "Live Mentor Support",
    text: "Friendly instructors guide students through each step with feedback, structure, and motivation."
  }
];

const courses = [
  {
    icon: "🧩",
    title: "Coding for Kids",
    level: "Ages 6–10",
    text: "Visual coding, logic games, creative problem solving, and fun beginner programming projects."
  },
  {
    icon: "🎮",
    title: "Game Development",
    level: "Ages 10–14",
    text: "Learn how to create games with levels, events, scoring systems, and interactive logic."
  },
  {
    icon: "🌐",
    title: "Web Development",
    level: "Ages 12–18",
    text: "Build modern websites using HTML, CSS, JavaScript, responsive design, and UI thinking."
  },
  {
    icon: "🐍",
    title: "Python Programming",
    level: "Ages 11–18",
    text: "Master variables, loops, conditions, functions, and practical project-based coding."
  },
  {
    icon: "🤖",
    title: "Robotics Basics",
    level: "Ages 8–16",
    text: "Discover sensors, automation ideas, movement logic, and how robots respond to commands."
  },
  {
    icon: "🧠",
    title: "AI & Future Tech",
    level: "Ages 14–18",
    text: "Explore introductory AI ideas, innovation thinking, and future technology concepts."
  }
];

const pricing = [
  {
    title: "Starter",
    price: "$39",
    subtitle: "per month",
    features: [
      "1 live session each week",
      "Beginner-friendly lessons",
      "Course materials included",
      "Parent progress updates"
    ],
    popular: false
  },
  {
    title: "Growth Offer",
    price: "$69",
    subtitle: "per month",
    features: [
      "2 live sessions each week",
      "Coding + project practice",
      "Priority mentor support",
      "Free trial session included",
      "Limited-time special offer"
    ],
    popular: true
  },
  {
    title: "Advanced",
    price: "$99",
    subtitle: "per month",
    features: [
      "3 live sessions each week",
      "Advanced coding path",
      "Robotics activities included",
      "Portfolio guidance and feedback"
    ],
    popular: false
  }
];

const testimonials = [
  {
    name: "Maya's Parent",
    text: "The lessons are clear, modern, and exciting. My daughter became more confident in problem solving and coding."
  },
  {
    name: "Omar, Age 13",
    text: "I loved building games and learning how websites work. The robotics part made everything more fun."
  },
  {
    name: "Rami's Parent",
    text: "The free trial was useful and professional. It helped us choose the best level and course for my son."
  }
];

function Navbar() {
  const scrolled = useNavbarScroll();

  return (
    <nav className={`navbar navbar-expand-lg fixed-top ${scrolled ? "scrolled" : ""}`}>
      <div className="container">
        <a className="navbar-brand d-flex align-items-center gap-3" href="#home">
          <img src={logo} className="logo-icon" alt="JuniorCode logo" />
          <div className="logo-text">
            <div className="logo-title">
              JuniorCode <span className="code-symbol">&lt;/&gt;</span>
            </div>
            <div className="logo-sub">
              LEARN • BUILD • GROW
            </div>
          </div>
        </a>

        <button
          className="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNav"
        >
          <span className="navbar-toggler-icon"></span>
        </button>

        <div className="collapse navbar-collapse" id="mainNav">
          <ul className="navbar-nav ms-auto align-items-lg-center gap-lg-2">
            <li className="nav-item">
              <a className="nav-link" href="#about">About</a>
            </li>

            <li className="nav-item">
              <a className="nav-link" href="#courses">Courses</a>
            </li>

            <li className="nav-item">
              <a className="nav-link" href="#trial">Free Trial</a>
            </li>

            <li className="nav-item">
              <a className="nav-link" href="#pricing">Pricing</a>
            </li>

            <li className="nav-item">
              <a className="nav-link" href="login.php">Login</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>
  );
}

function Hero() {
  return (
    <section className="hero" id="home">
      <div className="container">
        <div className="card hero-card reveal active">
          <div className="row g-0 align-items-stretch">
            <div className="col-lg-6 p-4 p-md-5 d-flex flex-column justify-content-center">
              <span className="hero-badge mb-3">Modern Tech Learning for Kids & Juniors</span>

              <h1 className="hero-title">
                Learn <span className="gradient-text">coding, robotics,</span> and future skills in a fun way.
              </h1>

              <p className="hero-text mb-4">
                A modern educational website for children and teens from 6 to 18 years old who are interested in
                programming, web development, Python, game creation, and robotics through interactive lessons and projects.
              </p>

              <div className="mb-3">
                <span className="age-pill">6–10 Kids Track</span>
                <span className="age-pill">11–14 Junior Track</span>
                <span className="age-pill">15–18 Teen Track</span>
              </div>

              <div className="d-flex flex-wrap gap-3 mb-4">
                <a href="#trial" className="btn btn-main">Start Free Trial</a>
                <a href="#courses" className="btn btn-outline-custom">Explore Courses</a>
              </div>

              <div className="row g-3">
                <div className="col-sm-4">
                  <div className="mini-stat">
                    <div className="fw-bold fs-3">12+</div>
                    <div className="text-muted">Course tracks</div>
                  </div>
                </div>
                <div className="col-sm-4">
                  <div className="mini-stat">
                    <div className="fw-bold fs-3">Live</div>
                    <div className="text-muted">Interactive classes</div>
                  </div>
                </div>
                <div className="col-sm-4">
                  <div className="mini-stat">
                    <div className="fw-bold fs-3">Projects</div>
                    <div className="text-muted">Hands-on learning</div>
                  </div>
                </div>
              </div>
            </div>

            <div className="col-lg-6">
              <div className="hero-visual h-100">
                <div className="orb one"></div>
                <div className="orb two"></div>
                <div className="orb three"></div>

                <div className="floating-badge badge-top">🤖 Robotics + Coding Labs</div>
                <div className="floating-badge badge-bottom">🎓 Learn With AI Fun</div>

                <img src={heroImage} alt="JuniorCode Hero" className="hero-img" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function Features() {
  return (
    <section className="section-padding glass-section" id="about">
      <div className="container">
        <div className="text-center mb-5 reveal">
          <span className="section-badge mb-3">Why Families Choose JuniorCode</span>
          <h2 className="section-title">A modern platform built for young future creators</h2>
          <p className="section-text">
            This website is designed for kids and juniors who want to learn real tech skills in a creative,
            structured, and motivating environment.
          </p>
        </div>

        <div className="row g-4">
          {features.map((item, index) => (
            <div className="col-md-6 col-lg-3" key={index}>
              <div className={`feature-card p-4 reveal delay-${(index % 4) + 1}`}>
                <div className="icon-box">{item.icon}</div>
                <h5 className="fw-bold mb-3">{item.title}</h5>
                <p className="text-muted mb-0" style={{ lineHeight: "1.8" }}>{item.text}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function Courses() {
  return (
    <section className="section-padding" id="courses">
      <div className="container">
        <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-5 reveal">
          <div>
            <span className="section-badge mb-3">What The Website Offers</span>
            <h2 className="section-title mb-2">Courses in programming, robotics, and future technology</h2>
            <p className="text-muted mb-0" style={{ maxWidth: "760px", lineHeight: "1.8" }}>
              Each course is presented clearly so parents and students can understand the learning path and choose the best option.
            </p>
          </div>
          <a href="#pricing" className="btn btn-outline-custom">View Pricing</a>
        </div>

        <div className="row g-4">
          {courses.map((course, index) => (
            <div className="col-md-6 col-lg-4" key={index}>
              <div className={`course-card p-4 reveal delay-${(index % 4) + 1}`}>
                <div className="d-flex justify-content-between align-items-start mb-3">
                  <div className="icon-box">{course.icon}</div>
                  <span className="course-pill">{course.level}</span>
                </div>
                <h5 className="fw-bold mb-3">{course.title}</h5>
                <p className="text-muted mb-0" style={{ lineHeight: "1.8" }}>{course.text}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function TrialSection() {
  return (
    <section className="section-padding" id="trial">
      <div className="container">
        <div className="cta-card p-4 p-md-5 reveal">
          <div className="row align-items-center g-4">
            <div className="col-lg-7">
              <span className="section-badge mb-3">Free Trial Session</span>
              <h2 className="section-title">Let students try a class before choosing a plan</h2>
              <p className="text-muted mb-4" style={{ lineHeight: "1.8" }}>
                The free trial session helps students and parents understand the teaching style, check the level,
                and choose the most suitable program.
              </p>

              <div className="row g-3">
                <div className="col-md-6">
                  <div className="p-4 rounded-4 bg-light h-100">
                    <h6 className="fw-bold">Live Introduction</h6>
                    <p className="text-muted mb-0">Meet the instructor and explore a real sample class.</p>
                  </div>
                </div>
                <div className="col-md-6">
                  <div className="p-4 rounded-4 bg-light h-100">
                    <h6 className="fw-bold">Level Matching</h6>
                    <p className="text-muted mb-0">Receive a course recommendation based on age and level.</p>
                  </div>
                </div>
              </div>
            </div>

            <div className="col-lg-5">
              <div className="offer-box p-4 p-md-5">
                <h4 className="fw-bold mb-3 position-relative" style={{ zIndex: 2 }}>Reserve Your Trial</h4>

                <div className="mb-3">
                  <input type="text" className="form-control" placeholder="Student Name" />
                </div>

                <div className="mb-3">
                  <input type="email" className="form-control" placeholder="Parent Email" />
                </div>

                <div className="mb-3">
                  <select className="form-select">
                    <option>Choose Age Group</option>
                    <option>6–10</option>
                    <option>11–14</option>
                    <option>15–18</option>
                  </select>
                </div>

                <button className="btn btn-light-strong w-100">Book Free Trial</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function Pricing() {
  return (
    <section className="section-padding" id="pricing">
      <div className="container">
        <div className="text-center mb-5 reveal">
          <span className="section-badge mb-3">Payment Plans</span>
          <h2 className="section-title">Flexible pricing with a featured offer</h2>
          <p className="section-text">
            Present your monthly plans clearly and guide parents toward the best-value option.
          </p>
        </div>

        <div className="row g-4">
          {pricing.map((plan, index) => (
            <div className="col-lg-4" key={index}>
              <div className={`pricing-card reveal delay-${(index % 3) + 1} ${plan.popular ? "popular" : ""}`}>
                {plan.popular && <div className="popular-badge">Most Popular</div>}
                <h4 className="fw-bold">{plan.title}</h4>
                <div className="price">{plan.price}</div>
                <div className="text-muted mb-4">{plan.subtitle}</div>

                {plan.features.map((feature, idx) => (
                  <div className="pricing-item" key={idx}>✓ {feature}</div>
                ))}

                <div className="mt-4">
                  <a href="#trial" className={`btn w-100 ${plan.popular ? "btn-main" : "btn-outline-custom"}`}>
                    {plan.popular ? "Choose Offer" : "Choose Plan"}
                  </a>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function Testimonials() {
  return (
    <section className="section-padding">
      <div className="container">
        <div className="text-center mb-5 reveal">
          <span className="section-badge mb-3">Feedback</span>
          <h2 className="section-title">What students and parents can say</h2>
          <p className="section-text">
            A testimonial section makes the homepage feel more trusted, complete, and professional.
          </p>
        </div>

        <div className="row g-4">
          {testimonials.map((item, index) => (
            <div className="col-md-6 col-lg-4" key={index}>
              <div className={`testimonial-card reveal delay-${(index % 3) + 1}`}>
                <div className="stars">⭐⭐⭐⭐⭐</div>
                <p className="text-muted" style={{ lineHeight: "1.8" }}>
                  "{item.text}"
                </p>
                <h6 className="fw-bold mt-3 mb-0">{item.name}</h6>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function Footer() {
  return (
    <footer className="footer">
      <div className="container">
        <div className="row g-4 align-items-start">
          <div className="col-lg-4">
            <div className="d-flex align-items-center gap-3 mb-3">
              <div className="brand-icon">
                <img src={heroImage} alt="JuniorCode Logo" className="brand-logo-img" />
              </div>
              <div>
                <h5 className="mb-0">JuniorCode Academy</h5>
                <small className="text-secondary">Coding and robotics for ages 6 to 18</small>
              </div>
            </div>
            <p className="text-secondary">
              A modern homepage concept for a kids and juniors programming website built with React and Bootstrap.
            </p>
          </div>

          <div className="col-md-4 col-lg-2">
            <h6 className="mb-3">Pages</h6>
            <div className="d-flex flex-column gap-2">
              <a href="#home">Home</a>
              <a href="#about">About</a>
              <a href="#courses">Courses</a>
            </div>
          </div>

          <div className="col-md-4 col-lg-3">
            <h6 className="mb-3">Programs</h6>
            <div className="d-flex flex-column gap-2">
              <a href="#courses">Web Development</a>
              <a href="#courses">Python</a>
              <a href="#courses">Robotics</a>
            </div>
          </div>

          <div className="col-md-4 col-lg-3">
            <h6 className="mb-3">Action</h6>
            <div className="d-flex flex-column gap-2">
              <a href="#trial">Book Free Trial</a>
              <a href="#pricing">View Pricing</a>
              <a href="#home">Back to Top</a>
            </div>
          </div>
        </div>

        <hr className="border-secondary my-4" />
        <div className="text-center text-secondary">
          ©️ 2026 JuniorCode Academy. All rights reserved.
        </div>
      </div>
    </footer>
  );
}

function App() {
  useReveal();

  return (
    <>
      <Navbar />
      <Hero />
      <Features />
      <Courses />
      <TrialSection />
      <Pricing />
      <Testimonials />
      <Footer />
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
