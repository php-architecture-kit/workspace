import './app.css';
import { mountComponents } from './mount';
import LazyOperatorsPage from './lazy-operators/LazyOperatorsPage';
import ShowcaseHome from './showcase/ShowcaseHome';
import StateMachinePage from './state-machine/StateMachinePage';
import UuidPage from './uuid/UuidPage';

mountComponents({ LazyOperatorsPage, ShowcaseHome, StateMachinePage, UuidPage });
